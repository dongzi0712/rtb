<?php
class BaseController {
    public $db;
    public $auth;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->auth = new Auth();
    }
} 