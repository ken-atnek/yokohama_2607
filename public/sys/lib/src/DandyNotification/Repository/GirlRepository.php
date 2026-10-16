<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

final class GirlRepository extends AbstractRepository
{
    public function findById(int $castId): ?array
    {
        return $this->fetchOne(
            'SELECT g_id, name, att_shop, view FROM t_girl_master2 WHERE g_id = :cast_id LIMIT 1',
            ['cast_id' => $castId]
        );
    }

    public function findByCastForUpdate(int $castId): ?array
    {
        return $this->fetchOne(
            'SELECT g_id, name, att_shop, view FROM t_girl_master2 WHERE g_id = :cast_id LIMIT 1 FOR UPDATE',
            ['cast_id' => $castId]
        );
    }

    public function findByShopAndCast(string $shopId, int $castId): ?array
    {
        return $this->fetchOne(
            'SELECT g_id, name, att_shop, view
             FROM t_girl_master2
             WHERE att_shop = :shop_id
               AND g_id = :cast_id
             LIMIT 1',
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );
    }

    public function findByShopAndCastForUpdate(string $shopId, int $castId): ?array
    {
        return $this->fetchOne(
            'SELECT g_id, name, att_shop, view
             FROM t_girl_master2
             WHERE att_shop = :shop_id
               AND g_id = :cast_id
             LIMIT 1
             FOR UPDATE',
            [
                'shop_id' => $shopId,
                'cast_id' => $castId,
            ]
        );
    }
}
