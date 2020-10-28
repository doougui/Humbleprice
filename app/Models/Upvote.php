<?php

namespace App\Models;

use App\Core\Table;

class Upvote extends Table
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "upvote";
    }

    public function count(int $offerId): ?int
    {
        $sql = "SELECT
                    count(id)
                AS 
                    amount
                FROM
                    upvote
                WHERE
                    id_offer = :id_offer
        ";
        $sql = $this->db->prepare($sql);
        $sql->bindParam(":id_offer", $offerId, \PDO::PARAM_INT);
        $sql->execute();

        if ($sql->rowCount() > 0) {
            return $sql->fetch()["amount"];
        }

        return null;
    }

    public function upvoted(int $offerId, int $userId): bool
    {
        $sql = "SELECT
                    id
                FROM
                    upvote
                WHERE
                    id_offer = :id_offer
                AND
                    id_user = :id_user
        ";
        $sql = $this->db->prepare($sql);
        $sql->bindParam(":id_offer", $offerId, \PDO::PARAM_INT);
        $sql->bindParam(":id_user", $userId, \PDO::PARAM_INT);
        $sql->execute();

        if ($sql->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function add(int $offerId, int $userId): void
    {
        $sql = "INSERT INTO
                    {$this->table}
                    (id_offer, id_user)
                VALUES
                    (:id_offer, :id_user)
        ";
        $sql = $this->db->prepare($sql);
        $sql->bindParam(":id_offer", $offerId, \PDO::PARAM_INT);
        $sql->bindParam(":id_user", $userId, \PDO::PARAM_INT);
        $sql->execute();
    }

    public function remove(int $offerId, int $userId): void
    {
        $sql = "DELETE FROM
                    {$this->table}
                WHERE
                    id_offer = :id_offer
                AND
                    id_user = :id_user
        ";
        $sql = $this->db->prepare($sql);
        $sql->bindParam("id_offer", $offerId, \PDO::PARAM_INT);
        $sql->bindParam("id_user", $userId, \PDO::PARAM_INT);
        $sql->execute();
    }
}