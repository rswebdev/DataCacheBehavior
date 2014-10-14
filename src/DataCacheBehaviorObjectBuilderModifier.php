<?php
namespace Propel\Generator\Behavior\DataCacheBehavior;

use Propel\Generator\Behavior\DataCacheBehavior;
use Propel\Generator\Util\PhpParser;

class DataCacheBehaviorObjectBuilderModifier
{
    protected $behavior;
    protected $builder;
    protected $tableClassName;
    protected $objectClassName;
    protected $queryClassName;

    public function __construct(DataCacheBehavior $behavior)
    {
        $this->behavior = $behavior;
    }

    protected function setBuilder($builder)
    {
        $this->builder         = $builder;
        $this->tableClassName = "\\Map{$builder->getObjectClassName(true)}TableMap";
        $this->objectClassName = $builder->getObjectClassName(true);
        $this->queryClassName  = $builder->getQueryClassName(true);
    }

    public function objectMethods($builder)
    {
        $script = "";

        $this->setBuilder($builder);

        $this->addPurgeCache($script);
        $this->addCacheFetch($script);
        $this->addCacheStore($script);
        $this->addCacheDelete($script);
        $this->peerFilter($script);

        return $script;
    }

    public function peerFilter(&$script)
    {
        $parser = new PhpParser($script, true);

        $this->replaceDoDeleteAll($parser);

        $script = $parser->getCode();
    }

    protected function addPurgeCache(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function purgeCache()
{
    return \\Domino\\CacheStore\\Factory::factory('{$backend}')->clearByNamespace({$this->tableClassName}::TABLE_NAME);
}
        ";
    }

    protected function addCacheFetch(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function cacheFetch(\$key)
{
    \$result = \\Domino\\CacheStore\\Factory::factory('{$backend}')->get({$this->tableClassName}::TABLE_NAME, \$key);

    if (\$result !== null) {
        if (\$result instanceof \\ArrayAccess) {
            foreach (\$result as \$element) {
                if (\$element instanceof {$this->objectClassName}) {
                    {$this->tableClassName}::addInstanceToPool(\$element);
                }
            }
        } else if (\$result instanceof {$this->objectClassName}) {
            {$this->tableClassName}::addInstanceToPool(\$result);
        }
    }

    return \$result;
}
        ";
    }

    protected function addCacheStore(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function cacheStore(\$key, \$data, \$lifetime)
{
    return \\Domino\\CacheStore\\Factory::factory('{$backend}')->set({$this->tableClassName}::TABLE_NAME, \$key, \$data, \$lifetime);
}
        ";
    }

    protected function addCacheDelete(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function cacheDelete(\$key)
{
    return \\Domino\\CacheStore\\Factory::factory('{$backend}')->clear({$this->tableClassName}::TABLE_NAME, \$key);
}
        ";
    }

    protected function replaceDoDeleteAll(PhpParser &$parser)
    {
        $search  = "\$con->commit();";
        $replace = "\$con->commit();\n            {$this->objectClassName}::purgeCache();";
        $script  = $parser->findMethod('doDeleteAll');
        $script  = str_replace($search, $replace, $script);

        $parser->replaceMethod("doDeleteAll", $script);
    }

    public function postSave($builder)
    {
        return "{$builder->getObjectClassName()}::purgeCache();";
    }

    public function postDelete($builder)
    {
        return $this->postSave($builder);
    }
}
