<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = [
        'blockchain_id', 'miner', 'nonce', 'height', 'previous_hash', 'hash', 'status'
    ];

    /**
     * Create the Genesis Block.
     *
     * @return App\Block
     */
    public static function createGenesisBlock(Blockchain $blockchain)
    {   
        $height = $blockchain->addHeight();

        $genesisBlock = [
            'blockchain_id' => $blockchain->id,
            'miner' => null,
            'nonce' => 0,
            'height' => $height,
            'previous_hash' => 'genesis',
            'hash' => '',
            'status' => 'not_mined',
        ];

        $genesisBlock = Block::create($genesisBlock);
        $genesisBlock->mineBlock();
        
        return $genesisBlock;
    }

    /**
     * Create a Block.
     *
     * @return App\Block
     */
    public static function createBlock(Blockchain $blockchain)
    {   
        $height = $blockchain->addHeight();

        $block = [
            'blockchain_id' => $blockchain->id,
            'nonce' => '0',
            'height' => $height,
            'previous_hash' => $blockchain->getLatestBlock()->hash,
            'hash' => '',
            'status' => 'not_mined',
        ];

        $block = Block::create($block);

        return $block;
    }

    
    /**
     * Mine a Block.
     * 
     * @param int $nonce
     * @return App\Block
     */
    public function mineBlock()
    {
        if ($this->status == 'mined') {
            return $this;
        }

        $hash = $this->createValidHash();

        $this->nonce = $hash['nonce'];
        $this->hash = $hash['hash'];
        $this->status = 'mined';
        $this->save();
        
        return $this;
    }

    /**
     * Take all params and return a valid hash.
     * 
     * @param int $nonce
     * @return $hash
     */
    public function createValidHash($nonce = 0)
    {
        do {
            $hash = $this->buildHash($nonce++);
        } while (!$this->isValidHashDifficulty($hash));

        $data = [
            'nonce' => $nonce - 1,
            'hash' => $hash,
        ];
        return $data;
    }

    /**
     * Build Hash
     * 
     * @param int $nonce
     * @return $hash
     */
    public function buildHash($nonce)
    {
        $unHashed = $this->blockchain_id . $nonce . $this->previous_hash . $this->created_at;

        return hash('sha256', $unHashed);
    }

    /**
     * Verify if hash is valid.
     * 
     * @param int $nonce
     * @return $hash
     */
    public function isValidHashDifficulty($hash)
    {
        $hashArray = str_split($hash);

        for ($i = 0; $i < count($hashArray) - 1; $i++) {
          if ($hashArray[$i] !== "0") {
            break;
          }
        }

        return $i >= $this->blockchain->difficulty;
    }

    /**
     * Get Shortned Previous Hash.
     * 
     * @param int $length
     * @return string $hash
     */
    public function getShortnedHash($length = null)
    {   
        return $this->shortensHash($this->hash, $length);
    }
    /**
     * Get Shortned Hash.
     * 
     * @param int $length
     * @return string $hash
     */
    public function getShortnedPreviousHash($length = null)
    {   
        return $this->shortensHash($this->previous_hash, $length);
    }

    /**
     * Shortens given Hash.
     * 
     * @param string $hash
     * @param int $length
     * @return string $hash
     */
    public static function shortensHash($hash, $length = null)
    {   
        if (!$hash) {
            return '-';
        }
        
        if ($hash == 'genesis') {
            return $hash;
        }

        if (!$length) {
            $length = 15;
        }

        return substr($hash, 0, $length) . '...';
    }

    /**
     * Get Shortned Hash.
     * 
     * @param int $length
     * @return string $hash
     */
    public function getPreviousBlock()
    {  
        if ($this->height - 1 == -1) {
            return false;
        }

        return Block::where('height', $this->height - 1)->first();
    }

    /**
     * Builds the Root of The Merkle Tree to store in block
     * 
     * @return string $hash
     */
    public function buildMerkleTreeRoot()
    {  
        $root = $this->buildAllNodesAndGetRoot();
        
        if (!$root) {
            if ($this->previous_hash == 'genesis') {
                return 'Genesis Block';
            }
            return 'No transactions found';            
        }

        return is_array($root) ? $root[0] : $root;
    }

    public function buildAllNodesAndGetRoot()
    {
        $transactionCount = $this->transactions->count();
        if ($transactionCount == 0) {
            //Verify if have transactions
            return null;
        }

        if ($transactionCount == 1) {
            //Verify if have only 1 transaction. If does, return itself hashed
            return hash('sha256', $this->transactions[0]->hash);
        }

        foreach ($this->transactions as $transaction) {
            //Build the first hash list aka leafs
            $hashes[] = $transaction->hash;
        }

        do {
            //Build each row until have 1 last hash, that is the Root
            $hashes = $this->buildMerkleTreeLeafRow($hashes);
        } while (count($hashes) != 1);
        
        return $hashes;
    }

    /**
     * Builds the Row of Leafs
     * 
     * @return string
     */
    public function buildMerkleTreeLeafRow($hashes)
    {  
        $oldRow = $hashes;

        if ($this->isEven($hashes)) {
            $lastHash = array_pop($hashes);
        }
        
        for ($i = 0; $i < count($hashes); $i += 2) {
            $newRow[] = $this->buildMerkleTreeLeafNode($hashes[$i], $hashes[$i + 1]);
        }
        

        if ($this->isEven($oldRow)) {
            $newRow[] = $lastHash;
        }
        
        return $newRow;
    }
    
    /**
     * Builds the Leaf Nodes of The Merkle Tree
     * 
     * @return string
     */
    public function buildMerkleTreeLeafNode($h1, $h2 = null)
    {  
        return hash('sha256', $h1 . $h2);
    }

    /**
     * Check if the given array is even
     * 
     * @param array $hashes
     * @return int
     */
    public function isEven($hashes = null) {
        if (!$hashes) {
            $hashes = $this->transactions;
        }

        return count($hashes) % 2;
    }

    /**
     * Get the Blockchain of this Block.
     */
    public function blockchain()
    {
        return $this->belongsTo('App\Blockchain');
    }

    /**
     * Get the Transactions of this Block.
     */
    public function transactions()
    {
        return $this->hasMany('App\Transaction');
    }

}
