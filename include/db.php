<?php

require_once(__ROOT__."/include/secret.php");

class db {
    private $db;
    private $tip = false;
    
    public function __construct() {       
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if($this->db === false) {
            return false;
        }
        $this->db->set_charset(DB_ENCODING);
        return true;
    }
    public function __destruct() {
        $this->db->close();
        unset($this->db);
    }
    private function connect() {
        if ($this->db === true) {
            $this->db->close();
        }
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if($this->db === false) {
            return false;
        }
        return true;
    }
    private function ref_vals($arr) {
        $refs = array();
        foreach ($arr as $key => $val) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    /*
     * Example:
     * $res = $db->query("SELECT id FROM table WHERE parent = 1");
     * foreach ($res as $row) {
     *   $row_id = $row->id;
     * }
     */
    public function query($query) {
        if (!isset($this->db)) {
            $this->connect();
        }
        
        $res = $this->db->query($query);
        if ($res === false) {
            return false;
        }
        
        $rows = array();
        while ($row = $res->fetch_object()) {
            $rows[] = $row;
        }
        
        return $rows;
    }
    /*
     * Example:
     * $row = $db->get_row("SELECT id FROM table WHERE static = 2 LIMIT 1");
     * $id = $row->id;
     */
    public function get_row($query) {
        if (!isset($this->db)) {
            $this->connect();
        }
        if (substr($query, strlen($query) - 7) != "LIMIT 1") {
            $query .= " LIMIT 1";
        }
        
        $res = $this->db->query($query);
        if ($res === false) {
            return false;
        }
        $row = $res->fetch_object();
        
        return $row;
    }
    /*
     * Example:
     * $res = $db->select("SELECT id FROM table WHERE parent = ?", array($parent_id), "i");
     * foreach ($res as $row) {
     *   $row_id = $row->id;
     * }
     */
    public function select($query, $data, $format) {
        if (!isset($this->db)) {
            $this->connect();
        }
        if ($stmt = $this->db->prepare($query)) {
            array_unshift($data, $format);
            call_user_func_array(array($stmt, "bind_param"), $this->ref_vals($data));
            $stmt->execute();
            
            $res = $stmt->get_result();
            if ($res === false) {
                return false;
            }
            
            $rows = array();
            while ($row = $res->fetch_object()) {
                $rows[] = $row;
            }
            $stmt->close();
            
            return $rows;
        }
        return false;
    }
    /*
     * Example:
     * $row = $db->select_row("SELECT id FROM table WHERE id = ? LIMIT 1", array($id), "i");
     * $id = $row->id;
     */
    public function select_row($query, $data, $format) {
        if (!isset($this->db)) {
            $this->connect();
        }
        if (substr($query, strlen($query) - 7) != "LIMIT 1") {
            $query .= " LIMIT 1";
        }
        if ($stmt = $this->db->prepare($query)) {
            array_unshift($data, $format);
            call_user_func_array(array($stmt, "bind_param"), $this->ref_vals($data));
            $stmt->execute();
            
            $res = $stmt->get_result();
            if ($res === false) {
                return false;
            }
            
            $row = $res->fetch_object();
            $stmt->close();
            
            return $row;
        }
        return false;
    }
    /*
     * Example:
     * $insert_id = $db->insert("INSERT INTO table (id, username, email) VALUES (?, ?, ?)", array($id, $username, $email), "iss");
     */
    public function insert($query, $data, $format) {
        if (!isset($this->db)) {
            $this->connect();
        }
        if ($stmt = $this->db->prepare($query)) {
            array_unshift($data, $format);
            call_user_func_array(array($stmt, "bind_param"), $this->ref_vals($data));
            $insert_id = 0;
            $stmt->execute();
            $insert_id = $stmt->insert_id;
            
            if ($stmt->affected_rows) {
                $stmt->close();
                return $insert_id;
            }
            $stmt->close();
            return false;
        }
        return false;
    }
    /*
     * Example:
     * if (!$db->update("UPDATE table SET email = ? WHERE id = ? LIMIT 1", array($email, $id), "si"))
     *   die("Error");
     */
    public function update($query, $data, $format) {
        if (!isset($this->db)) {
            $this->connect();
        }
        if ($stmt = $this->db->prepare($query)) {
            array_unshift($data, $format);
            try {
                if (!call_user_func_array(array($stmt, "bind_param"), $this->ref_vals($data))) {
                    throw new Exception();
                }
                if (!$stmt->execute()) {
                    throw new Exception();
                }
            }
            catch(Exception $e) {
                return false;
            }
            
            if ($stmt->affected_rows) {
                $rows = 0 + $stmt->affected_rows;
                $stmt->close();
                return $rows;
            }
            $stmt->close();
            return true;
        }
        return false;
    }
    /*
     * Example:
     * if (!$db->update("DELETE FROM table WHERE id = ? LIMIT 1", array($id), "i"))
     *   die("Error");
     */
    public function delete($query, $data, $format) {
        if (!isset($this->db)) {
            $this->connect();
        }
        if ($stmt = $this->db->prepare($query)) {
            array_unshift($data, $format);
            call_user_func_array(array($stmt, "bind_param"), $this->ref_vals($data));
            $stmt->execute();
            
            if ($stmt->affected_rows) {
                $stmt->close();
                return true;
            }
            $stmt->close();
            return false;
        }
        return false;
    }
    /*
     * Transactions.
     */
    public function begin_transaction() {
        $ret = $this->db->autocommit(false);
        $this->tip = true;
        register_shutdown_function(array($this, "__shutdown_check"));
    }
    
    public function __shutdown_check() {
        if ($this->tip) {
            $this->rollback();
        }
    }
    public function commit() {
        $ret = $this->db->commit();
        $this->tip = false;
        return true;
    }
    public function rollback() {
        $ret = $this->db->rollback();
        $this->tip = false;
        return true;
    }
    /*
     * End of transactions.
     */
}

?>