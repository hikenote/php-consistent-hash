<?php
namespace ConsistentHash;
use ConsistentHash\HashInterface;

class Md5Hasher implements HashInterface
{
    public function hash($string)
    {
        return substr(md5($string), 0, 8);
    }
}