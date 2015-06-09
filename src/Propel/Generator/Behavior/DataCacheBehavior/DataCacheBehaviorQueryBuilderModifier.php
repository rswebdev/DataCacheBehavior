<?php
namespace RSWebDev\Propel\Generator\Behavior\DataCacheBehavior;
/**
 * Propel Data Cache Behavior
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Util\PhpParser;

/**
 * Propel Data Cache Behavior Query Builder Modifier
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */
class DataCacheBehaviorQueryBuilderModifier
{
    protected $behavior;
    protected $builder;
    protected $tableClassName;
    protected $objectClassName;
    protected $queryClassName;

    public function __construct($behavior)
    {
        $this->behavior = $behavior;
    }

    protected function setBuilder($builder)
    {
        $this->builder = $builder;
        $this->tableClassName = "{$builder->getObjectClassName(true)}TableMap";
        $this->objectClassName = $builder->getObjectClassName(true);
        $this->queryClassName = $builder->getQueryClassName(true);
    }

    public function postUpdateQuery(QueryBuilder $builder)
    {
        $this->setBuilder($builder);

        return "{$this->objectClassName}::purgeCache();";
    }

    public function postDeleteQuery($builder)
    {
        return $this->postUpdateQuery($builder);
    }

    public function queryAttributes($builder)
    {
        $lifetime   = $this->behavior->getParameter("lifetime");
        $auto_cache = $this->behavior->getParameter("auto_cache");

        $script = "
protected \$cacheKey      = '';
protected \$cacheLocale   = '';
protected \$cacheEnable   = {$auto_cache};
protected \$cacheLifeTime = {$lifetime};
        ";

        return $script;
    }

    public function queryMethods($builder)
    {
        $builder->declareClasses('\Propel\Runtime\Propel');

        $this->setBuilder($builder);

        $script = "";
        $this->addSetCacheEnable($script);
        $this->addSetCacheDisable($script);
        $this->addIsCacheEnable($script);
        $this->addGetCacheKey($script);
        $this->addSetCacheKey($script);
        $this->addSetLocale($script);
        $this->addSetLifeTime($script);
        $this->addGetLifeTime($script);
        $this->addFind($script);
        $this->addFindOne($script);

        return $script;
    }

    public function queryFilter(&$script)
    {
        $parser = new PhpParser($script, true);

        $this->replaceFindPk($parser);

        $script = $parser->getCode();
    }

    protected function addSetCacheEnable(&$script)
    {
        $script .= "
public function setCacheEnable()
{
    \$this->cacheEnable = true;

    return \$this;
}
        ";
    }

    protected function addSetCacheDisable(&$script)
    {
        $script .= "
public function setCacheDisable()
{
    \$this->cacheEnable = false;

    return \$this;
}
        ";
    }

    protected function addIsCacheEnable(&$script)
    {
        $script .= "
public function isCacheEnable()
{
    return (bool)\$this->cacheEnable;
}
        ";
    }

    protected function addGetCacheKey(&$script)
    {
        $script .= "
public function getCacheKey()
{
    if (\$this->cacheKey) {
        return \$this->cacheKey;
    }
    \$params      = array();
    \$sql_hash    = hash('md4', \$this->createSelectSql(\$params));
    \$params_hash = hash('md4', json_encode(\$params));
    \$locale      = \$this->cacheLocale ? '_' . \$this->cacheLocale : '';
    \$this->cacheKey = \$sql_hash . '_' . \$params_hash . \$locale;

    return \$this->cacheKey;
}
        ";
    }

    protected function addSetLocale(&$script)
    {
        $script .= "
public function setCacheLocale(\$locale)
{
    \$this->cacheLocale = \$locale;

    return \$this;
}
";
    }

    protected function addSetCacheKey(&$script)
    {
        $script .= "
public function setCacheKey(\$cacheKey)
{
    \$this->cacheKey = \$cacheKey;

    return \$this;
}
";
    }

    protected function addSetLifeTime(&$script)
    {
        $script .= "
public function setLifeTime(\$lifetime)
{
    \$this->cacheLifeTime = \$lifetime;

    return \$this;
}
        ";
    }

    protected function addGetLifeTime(&$script)
    {
        $script .= "
public function getLifeTime()
{
    return \$this->cacheLifeTime;
}
        ";
    }

    protected function addFind(&$script)
    {
        $script .= "
/**
 * Issue a SELECT query based on the current ModelCriteria
 * and format the list of results with the current formatter
 * By default, returns an array of model objects
 *
 * @param ConnectionInterface \$con an optional connection object
 *
 * @return PropelObjectCollection|array|mixed the list of results, formatted by the current formatter
 */
public function find(ConnectionInterface \$con = null)
{
    if (\$this->isCacheEnable() && \$cache = {$this->objectClassName}::cacheFetch(\$this->getCacheKey())) {
        if (\$cache instanceof \\Propel\\Runtime\\Collection\\Collection) {
            \$formatter = \$this->getFormatter()->init(\$this);
            \$cache->setFormatter(\$formatter);
        }

        return \$cache;
    }

    if (\$con === null) {
        \$con = Propel::getServiceContainer()->getReadConnection(\$this->getDbName());
    }
    \$this->basePreSelect(\$con);
    \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
    \$dataFetcher = \$criteria->doSelect(\$con);

    \$data = \$criteria->getFormatter()
            ->init(\$criteria)
            ->format(\$dataFetcher);

    if (\$this->isCacheEnable()) {
        {$this->objectClassName}::cacheStore(\$this->getCacheKey(), \$data, \$this->getLifeTime());
    }

    return \$data;
}
        ";
    }

    protected function addFindOne(&$script)
    {
        $script .= "
/**
 * Issue a SELECT ... LIMIT 1 query based on the current ModelCriteria
 * and format the result with the current formatter
 * By default, returns a model object
 *
 * @param ConnectionInterface \$con an optional connection object
 *
 * @return mixed the result, formatted by the current formatter
 */
public function findOne(ConnectionInterface \$con = null)
{
    if (\$this->isCacheEnable() && \$cache = {$this->objectClassName}::cacheFetch(\$this->getCacheKey())) {
        if (\$cache instanceof {$this->objectClassName}) {
            return \$cache;
        }
    }

    if (\$con === null) {
        \$con = Propel::getServiceContainer()->getReadConnection(\$this->getDbName());
    }
    \$this->basePreSelect(\$con);
    \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
    \$criteria->limit(1);
    \$dataFetcher = \$criteria->doSelect(\$con);

    \$data = \$criteria->getFormatter()
            ->init(\$criteria)
            ->formatOne(\$dataFetcher);

    if (\$this->isCacheEnable()) {
        {$this->objectClassName}::cacheStore(\$this->getCacheKey(), \$data, \$this->getLifeTime());
    }

    return \$data;
}
        ";
    }

    protected function replaceFindPk(PhpParser &$parser)
    {
        $search  = "return \$this->findPkSimple(\$key, \$con);";
        $replace = "return \$this->filterByPrimaryKey(\$key)->findOne(\$con);";
        $script  = $parser->findMethod('findPk');
        $script  = str_replace($search, $replace, $script);

        $parser->replaceMethod('findPk', $script);
    }
}
