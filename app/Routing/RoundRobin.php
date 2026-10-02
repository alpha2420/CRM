<?php

namespace App\Routing;

/**
 * Takes turns fairly: the next id after the last one picked, wrapping
 * around to the start.
 */
final class RoundRobin
{
    /**
     * @param  list<int>  $ids  in a stable order
     */
    public static function next(array $ids, ?int $last): ?int
    {
        if ($ids === []) {
            return null;
        }

        sort($ids);

        foreach ($ids as $id) {
            if ($last === null || $id > $last) {
                return $id;
            }
        }

        return $ids[0];
    }
}
