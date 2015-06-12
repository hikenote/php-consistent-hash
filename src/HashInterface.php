<?php
namespace ConsistentHash;

interface HashInterface
{
    public function hash($string);
}