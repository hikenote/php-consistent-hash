<?php
namespace ConsistentHash;
use ConsistentHash\HashInterface;
use ConsistentHash\Crc32Hasher;
use ConsistentHash\Md5Hasher;
use ConsistentHash\Sha1Hasher;
use ConsistentHash\Exception;

//主类,这里用到了虚拟节点,是为了更好的分散key的排布
class ConsistentHash
{
    //虚拟节点数
    private $_replicas = 32;

    //hash算法的名字,默认使用CRC32
    private $_hasher;

    //目标个数,可理解为服务器个数
    private $_targetCount = 0;

    //服务器hash值作为key,服务器地址作为value的数组
    private $_positionToTarget = [];

    //服务器对应的hash,和上个数组正好对应
    private $_targetToPositions = [];

    //_positionToTarget 这个数组是否已做了排序
    private $_positionToTargetSorted = false;

    //构造函数,初始化hash算法和虚拟节点数
    public function __construct(HashInterface $hasher = null, $replicas = null)
    {
        $this->_hasher = $hasher ? $hasher : new Crc32Hasher();
        if (!empty($replicas)) $this->_replicas = $replicas;
    }

    //添加服务器
    public function addTarget($target)
    {
        //已存在这个服务器就直接抛异常
        if (isset($this->_targetToPositions[$target]))
        {
            throw new Exception("Target '$target' already exists.");
        }

        $this->_targetToPositions[$target] = [];

        // hash the target into multiple positions
        for ($i = 0; $i < $this->_replicas; $i++)
        {
            //根据服务器和虚拟节点数,给每个服务器的虚拟节点做hash,并存储到数组
            $position = $this->_hasher->hash($target . $i);
            $this->_positionToTarget[$position] = $target; // lookup
            $this->_targetToPositions[$target] []= $position; // target removal
        }

        //添加服务器后标志尚未排序
        $this->_positionToTargetSorted = false;

        //服务器个数加1
        $this->_targetCount++;

        return $this;
    }

    //没有特别,只是遍历数组,调用addTarget
    public function addTargets($targets)
    {
        foreach ($targets as $target)
        {
            $this->addTarget($target);
        }

        return $this;
    }

    //移除服务器
    public function removeTarget($target)
    {
        if (!isset($this->_targetToPositions[$target]))
        {
            throw new Exception("Target '$target' does not exist.");
        }

        //遍历_targetToPositions 找到给定服务器对应的position并删除
        foreach ($this->_targetToPositions[$target] as $position)
        {
            unset($this->_positionToTarget[$position]);
        }

        unset($this->_targetToPositions[$target]);

        $this->_targetCount--;

        return $this;
    }

    public function getAllTargets()
    {
        return array_keys($this->_targetToPositions);
    }

    //查找位置,这个只是个接口,作用不大.
    public function lookup($resource)
    {
        $targets = $this->lookupList($resource, 1);
        if (empty($targets)) throw new Exception('No targets exist');
        return $targets[0];
    }

    //主要靠这个函数,找节点位置,resource是给定的key,requestedCount是需要返回的节点数,默认用1
    public function lookupList($resource, $requestedCount=1)
    {
        if (!$requestedCount)
            throw new Exception('Invalid count requested');

        // handle no targets
        if (empty($this->_positionToTarget))
            return [];

        // optimize single target
        if ($this->_targetCount == 1)   //是一个服务器就直接返回数据了
            return array_unique(array_values($this->_positionToTarget));

        // hash resource to a position
        $resourcePosition = $this->_hasher->hash($resource);

        $results = []; //返回的结果集
        $collect = false;   //是否找到结果

        $this->_sortPositionTargets(); //做用key做数组_positionToTarget的排序

        // search values above the resourcePosition
        foreach ($this->_positionToTarget as $key => $value)
        {
            // start collecting targets after passing resource position
            if (!$collect && $key > $resourcePosition)  //尚未找到,并且服务器键大于资源位置  => 找到了
            {
                $collect = true;
            }

            // only collect the first instance of any target
            if ($collect && !in_array($value, $results))    //找到了,并且在服务器不在结果集中 => 将服务器加入结果集
            {
                $results []= $value;
            }

            // return when enough results, or list exhausted
            if (count($results) == $requestedCount || count($results) == $this->_targetCount) //结果集的数等于requestedCount或者等于服务器数 => 返回结果集
            {
                return $results;
            }
        }

        // loop to start - search values below the resourcePosition
        foreach ($this->_positionToTarget as $key => $value)    //走到这里表示没有找到,需要从头轮回
        {
            if (!in_array($value, $results))    //直接取第一个,当服务器不在结果集中 => 将服务器加入结果集
            {
                $results []= $value;
            }

            // return when enough results, or list exhausted
            if (count($results) == $requestedCount || count($results) == $this->_targetCount) //结果集的数等于requestedCount或者等于服务器数 => 返回结果集
            {
                return $results;
            }
        }

        return $results;
    }

    public function __toString()
    {
        return sprintf(
            '%s{targets:[%s]}',
            get_class($this),
            implode(',', $this->getAllTargets())
        );
    }

    private function _sortPositionTargets()
    {
        if (!$this->_positionToTargetSorted)
        {
            ksort($this->_positionToTarget, SORT_REGULAR);
            $this->_positionToTargetSorted = true;
        }
    }

}



