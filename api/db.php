<?php

const HOST = "localhost" ;
const DB = "chat_app" ;
const USERNAME = "root" ;
const PASSWORD = "" ;

class DB {
    private static $instance = null ;
    private $conn ;

    private function __construct()
    {
        $dns = "mysql:host=".HOST.";dbname=".DB.";charset=utf8mb4";
        $pdo = new PDO($dns, USERNAME, PASSWORD);
        $this->conn = $pdo;
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public static function getInstance(){
        if(self::$instance) {
            return self::$instance ;
        }
        else {
            self::$instance = new DB();
            return self::$instance ;
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    private function __clone() {}

    public static function queryExecuter($query,$params,$returntype){
        $db = DB::getInstance();
        $con = $db->getConnection();
        $stm = $con->prepare($query);
        $result = $stm->execute($params);
        switch($returntype){
            case 'id' :
                return $con->lastInsertId();
            case 'check' :
                return $result ;
            case 'fetch' :
                return $stm->fetch(PDO::FETCH_ASSOC);
            case 'fetchall' :
                return $stm->fetchAll(PDO::FETCH_ASSOC);
            default :
                return false ;
        }
    }
}
