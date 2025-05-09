<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App\State;

/**
 * Skip List and Filter List for collecting and comparing objects created by ObjectManager
 */
class SkipListAndFilterList
{
    /**
     * @var array|null
     */
    private ?array $skipList = null;

    /**
     * @var array
     */
    private array $filtersByClassNameAndServiceNameCache = [];

    /**
     * @var array|null
     */
    private ?array $filterList = null;

    /**
     * Filters properties by the list of property filters
     *
     * @param array $properties
     * @param array $propertiesToFilterList
     * @return array
     */
    public function filterProperties(array $properties, array $propertiesToFilterList): array
    {
        return array_diff_key($properties, $propertiesToFilterList);
    }

    /**
     * Gets skipList, loading it if needed
     *
     * @param string $operationName
     * @param CompareType $compareType
     * @return array
     */
    public function getSkipList(string $operationName, CompareType $compareType): array
    {
        if ($this->skipList === null) {
            $skipListList = [];
            foreach (glob(__DIR__ . '/../../_files/state-skip-list*.php') as $skipListFile) {
                $skipListList[] = include($skipListFile);
            }
            $this->skipList = array_merge_recursive(...$skipListList);
        }
        $skipLists = [$this->skipList['*']];
        if (array_key_exists($operationName, $this->skipList)) {
            $skipLists[] = $this->skipList[$operationName];
        }
        if (CompareType::CompareConstructedAgainstCurrent == $compareType) {
            if (array_key_exists($operationName . '-fromConstructed', $this->skipList)) {
                $skipLists[] = $this->skipList[$operationName . '-fromConstructed'];
            }
            if (array_key_exists('*-fromConstructed', $this->skipList)) {
                $skipLists[] = $this->skipList['*-fromConstructed'];
            }
        }
        return array_merge(...$skipLists);
    }

    /**
     * Gets filterList, loading it if needed
     *
     * @return array
     */
    public function getFilterList(): array
    {
        if ($this->filterList === null) {
            $filterListList = [];
            foreach (glob(__DIR__ . '/../../_files/state-filter-list*.php') as $filterListFile) {
                $filterListList[] = include($filterListFile);
            }
            $this->filterList = array_merge_recursive(...$filterListList);
        }
        return $this->filterList;
    }

    /**
     * Gets the list of properties to filter for a given class name and service name
     *
     * @param string $className
     * @param string $serviceName
     * @return array
     */
    public function getFilterListByClassNameAndServiceName(string $className, string $serviceName) : array
    {
        if ($this->filtersByClassNameAndServiceNameCache[$className][$serviceName] ?? false) {
            return $this->filtersByClassNameAndServiceNameCache[$className][$serviceName];
        }
        $filterList = $this->getFilterList();
        $filterListParentClasses = $filterList['parents'] ?? [];
        $filterListServices = $filterList['services'] ?? [];
        $filterListAll = $filterList['all'] ?? [];
        $propertiesToFilterList = [];
        if (isset($filterListServices[$serviceName])) {
            $propertiesToFilterList[] = $filterListServices[$serviceName];
        }
        foreach ($filterListParentClasses as $parentClass => $excludeProperties) {
            if (is_a($className, $parentClass, true)) {
                $propertiesToFilterList[] = $excludeProperties;
            }
        }
        if ($filterListAll) {
            $propertiesToFilterList[] = $filterListAll;
        }
        $propertiesToFilter = array_merge(...$propertiesToFilterList);
        $this->filtersByClassNameAndServiceNameCache[$className][$serviceName] = $propertiesToFilter;
        return $propertiesToFilter;
    }
}
