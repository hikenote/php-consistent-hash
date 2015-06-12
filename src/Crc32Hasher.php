<?php
namespace ConsistentHash;
use ConsistentHash\HashInterface;

class Crc32Hasher implements HashInterface
{
    public function hash($string)
    {
        return crc32($string);
    }
}