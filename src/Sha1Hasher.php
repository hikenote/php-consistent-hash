<?php
namespace ConsistentHash;
use ConsistentHash\HashInterface;


class Sha1Hasher implements HashInterface
{
    public function hash($string)
    {
        return substr(sha1($string), 0, 8);
    }
}