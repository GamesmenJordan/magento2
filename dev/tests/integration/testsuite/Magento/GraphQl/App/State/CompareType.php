<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App\State;

/**
 * What type of comparison
 */
enum CompareType
{
    case CompareBetweenRequests;
    case CompareConstructedAgainstCurrent;
}
