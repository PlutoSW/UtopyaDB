<?php
//Developed by Yasin ERSOY yasin@freelyshout.com
class UtopyaRegexTurkish
{
    public function __construct($pattern)
    {
        $arananlar = array('/I/', '/İ/', '/Ş/', '/Ö/', '/Ü/', '/Ğ/', '/Ç/');
        $yeniler   = array('i', 'i', 'ş', 'ö', 'ü', 'ğ', 'ç');
        ksort($arananlar);
        ksort($yeniler);
        $pattern   = preg_replace($arananlar, $yeniler, $pattern);
        $this->ret = strtolower($pattern);
        return $this->ret;
    }
    public function __toString()
    {
        return $this->ret;
    }
}
class dbOperate
{
    protected function isRegex($string)
    {
        $regex = "/^\/.+\/[a-z]*$/i";
        return preg_match($regex, $string);
    }
    protected function isJson($string)
    {
        return !empty($string) && is_string($string) && is_array(json_decode($string, true)) && json_last_error() == 0;
    }
    protected function translate($p)
    {
        return preg_replace("/[^[:alnum:]+]/i", "-", trim($p));
    }

    protected function error($error, $p = '')
    {
        file_put_contents($this->utopyaPath . "files/utopya_error_log", $error . ": " . $p . "\n", FILE_APPEND);
        file_put_contents($this->utopyaPath . "files/last_error_log", $error . ": " . $p);
    }
    public function lastError()
    {
        return file_get_contents($this->utopyaPath . "files/last_error_log");
    }
    protected function saveid($id, $schema)
    {
        $ids = json_decode(file_get_contents($this->dbPath . 'last-ids.json'), true);

        if (!$ids) {
            $ids = array();
        }
        $ids[$schema] = $id;
        file_put_contents($this->dbPath . 'last-ids.json', json_encode($ids));
    }
    public function restoreDB($name)
    {
        $zip = new ZipArchive;
        if ($zip->open($this->utopyaPath . $name) === true) {
            $zip->extractTo($this->dbPath);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }
    public function backupDB()
    {
        if (!extension_loaded('zip')) {
            return false;
        }
        $zip = new ZipArchive();
        if (!$zip->open($this->utopyaPath . $this->db . "-" . date("d-m-Y-H-i-s") . ".zip", ZIPARCHIVE::CREATE)) {
            return false;
        }
        $source = str_replace('\\', '/', realpath($this->dbPath));
        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                    continue;
                }
                $file = realpath($file);
                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } else if (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        return $zip->close();
    }
    public function findRegex($pattern, $input, $flags = 0, $e)
    {
        if ($e) {
            return array_merge(
                array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags))),
                preg_grep($pattern, $input, $flags)
            );
        } else {
            $input = new UtopyaRegexTurkish($input);
            return array_merge(
                array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags))),
                preg_grep($pattern, $input, $flags)
            );
        }
    }
}
class Schemas extends dbOperate
{
    protected function schemaExist($schema)
    {
        $schemas = $this->schemas;
        if (in_array($schema, $schemas)) {
            return true;
        } else {
            return false;
        }
    }
    protected function updateSchemas()
    {
        $sch = array();
        foreach (glob($this->dbPath . "*", GLOB_ONLYDIR) as $dir) {
            $sch[] = basename($dir);
        }
        $this->schemas = $sch;
    }
    public function getSchemas()
    {
        return $this->schemas;
    }
    protected function lastID($schema)
    {
        $ids = json_decode(file_get_contents($this->dbPath . 'last-ids.json'), true);
        return $ids[$schema];
    }
    public function schema($schema)
    {
        if (!is_array($schema)) {
            $schema = $this->translate($schema);
            if ($this->schemaExist($schema)) {
                $this->schemaName = $schema;
                $this->schema     = $this->dbPath . $schema;
                return $this;
            } else {
                $this->createSchema($schema);
                return $this;
            }
        } else {
            $this->schema = $schema;
            return $this;
        }
    }
    public function createSchema($schema)
    {
        if ($this->schemaExist($schema)) {
            $this->error("Schema already exist", $schema);
            return false;
        } else {
            mkdir($this->dbPath . $schema);
            $this->updateSchemas();
            return $this->schema($schema);
        }
    }
    public function rename($newschemaname)
    {
        if (rename($this->schema, $this->dbPath . $newschemaname)) {
            return true;
        } else {
            $this->error("Schema not found", $this->schemaName);
            return false;
        }
    }
    public function drop()
    {
        try {
            $documents = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->schema, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($documents as $docInfo) {
                $todo = ($docInfo->isDir() ? 'rmdir' : 'unlink');
                $todo($docInfo->getRealPath());
            }
            rmdir($this->schema);
            return true;
        } catch (Exception $e) {
            $this->error("Schema not found", $this->schemaName);
        }
    }
    protected function documentExist($id)
    {
        if (file_exists($this->schema . "/" . $id . ".json")) {
            return true;
        } else {
            return false;
        }
    }
}

class Query extends Schemas
{
    private function operators($op, $key)
    {

        if ($key === '$or') {
            foreach ($op as $key) {
                foreach ($key as $ev => $value) {
                    if (!$this->isRegex($value)) {
                        $val .= '($data["' . $ev . '"] == "' . $value . '") || ';
                    } else {
                        $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $ev . '"]))) || ';
                    }
                }
            }
            $val = rtrim($val, ' || ');
        } else {
            $vals = array_values($op)[0];
            $val  = '';
            switch (array_keys($op)[0]) {
                case '$hasKey':
                    $val .= '(isset($data["' . $key . '"]' . ')) && ';
                    break;
                case '$gt':
                    $val .= '($data["' . $key . '"]' . ' > ' . $op . ') && ';
                    break;
                case '$lt':
                    $val .= '($data["' . $key . '"]' . ' < ' . $op . ') && ';
                    break;
                case '$in':
                    if (!$this->isRegex($vals)) {
                        if (is_array($vals)) {
                            foreach ($vals as $value) {
                                $val .= '(in_array("' . $value . '", $data["' . $key . '"])) || ';
                            }
                        } else {
                            $val .= '(in_array("' . $vals . '", $data["' . $key . '"])) && ';
                        }
                    } else {
                        foreach ($vals as $value) {
                            $val .= '($this->findRegex("' . $value . '", $data["' . $key . '"])) || ';
                        }
                    }
                    $val = rtrim($val, ' || ');
                    break;
                case '$or':
                    if (is_array($vals)) {
                        foreach ($vals as $value) {
                            if (!$this->isRegex($value)) {
                                $val .= '($data["' . $key . '"] == "' . $value . '") || ';
                            } else {
                                $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $key . '"]))) || ';
                            }
                        }
                    } else {
                        if (!$this->isRegex($vals)) {
                            $val .= '($data["' . $key . '"] == "' . $vals . '") || ';
                        } else {
                            $val .= '(preg_match("' . $vals . '", new UtopyaRegexTurkish($data["' . $key . '"]))) || ';
                        }
                    }
                    $val = rtrim($val, ' || ');
                    break;
            }
        }
        return $val;
    }
    protected function Condition($params)
    {
        $val = '';
        foreach (array_keys($params) as $key) {
            $value = $params[$key];
            $key   = (strstr($key, ".")) ? str_replace(".", '"]["', $key) : $key;
            if (is_array($value)) {
                $val .= $this->operators($value, $key);
                if ($val === '') {
                    foreach ($value as $w) {
                        if (!$this->isRegex($w)) {
                            $val .= '($data["' . $key . '"] == "' . $w . '") && ';
                        } else {
                            $val .= '(preg_match("' . $w . '", new UtopyaRegexTurkish($data["' . $key . '"]))) && ';
                        }
                    }
                }
            } else {
                if (!$this->isRegex($value)) {
                    $val .= '($data["' . $key . '"] == "' . $value . '") && ';
                } else {
                    $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $key . '"]))) && ';
                }
            }

        }
        $val = rtrim($val, ' && ');
        return rtrim($val);

    }
    public function query($params, $type)
    {
        if (!is_callable($params)) {
            $condition   = $this->Condition($params);
            $is_callable = false;
        } else {
            $is_callable = true;
        }
        $match = array();
        if (isset($type["limit"])) {
            $limit = $type["limit"];
        }
        if (isset($type["skip"])) {
            $skip = $type["skip"] + 1;
        }
        if (!is_array($this->schema)) {
            $files = glob($this->schema . "/*");
            natsort($files);
            if (isset($type["sort"]) && $type["sort"] === "desc") {
                arsort($files);
            }
            foreach ($files as $file) {
                if ($limit === 0) {
                    break;
                }

                $fileContents = file_get_contents($file);
                $data         = json_decode($fileContents, true);
                if ($is_callable) {
                    if ($params($data)) {
                        if (isset($type["skip"])) {
                            $skip--;
                        }
                        if ($skip > 0) {
                            continue;
                        }
                        if (isset($type["limit"])) {
                            $limit--;
                        }
                        array_push($match, $data);
                    }
                } else {
                    if (eval("return $condition;")) {
                        array_push($match, $data);
                        if (isset($type["limit"])) {
                            $limit--;
                        }
                        if (isset($type["skip"])) {
                            $skip--;
                        }
                    }
                }
            }
        } else {
            $fileContents = $this->schema;
            foreach ($fileContents as $data) {
                if ($is_callable) {
                    if ($params($data)) {
                        array_push($match, $data);
                    }
                } else {
                    if (eval("return $condition;")) {
                        array_push($match, $data);
                    }
                }
            }
        }
        if (!$match) {
            return false;
        } else {
            return $match;
        }
    }
    public function count($param, $type = null)
    {
        $files = glob($this->schema . "/*");
        if ($files) {
            return count($files);
        } else {
            return 0;
        }
    }
    public function find($param, $type = null)
    {
        $documents = $this->query($param, $type);
        if (!$documents) {
            //$this->error("Document not found", $this->schemaName . " " . print_r($param, true));
        }
        return new Result($documents);
    }
    public function findAll($skip = 0, $limit = null)
    {
        if (!is_array($this->schema)) {
            $datas = glob($this->schema . "/*");
            natsort($datas);
            $datas = array_reverse($datas);
            $data  = array();
            $limit = (!$l) ? $s : $l;
            $skip  = ($l) ? $s : 0;
            $datas = array_slice($datas, $skip, $limit);
            foreach ($datas as $file) {
                $file = json_decode(file_get_contents($file), true);
                array_push($data, $file);
            }
        } else {
            $data = $this->schema;
        }
        return new Result($data);
    }
    public function insert($params)
    {
        if (!$this->schemaExist($this->schemaName)) {
            $this->createSchema($this->schemaName) or die(lastError());
        }
        $params = (!is_array($params)) ? json_decode($params, true) : $params;
        $lastid = $this->lastID($this->schemaName) + 1;
        $params = array("id" => $lastid) + $params;
        try {
            file_put_contents($this->schema . "/" . $lastid . ".json", json_encode($params));
            $this->saveid($lastid, $this->schemaName);
            return (array) $params;
        } catch (Exception $e) {
            $this->error($e, $this->schemaName);
            return false;
        }

    }
    public function remove($query)
    {
        $file = false;
        if (is_numeric($query)) {
            if (!$this->documentExist($query)) {
                $this->error("Document not found", $this->schemaName . " (id=$query)");
                return false;
            }
            $file = $this->schema . "/" . $query . ".json";
            try {
                unlink($file);
                return true;
            } catch (Exception $e) {
                $this->error($e, $this->schemaName);
                return false;
            }
        } else {
            $document = $this->query($query);
            if (!$document) {
                $this->error("Documents not found", $this->schemaName . " " . print_r($query, true));
                return false;
            }
            foreach ($document as $d) {
                $file = $this->schema . "/" . $d["id"] . ".json";
                try {
                    unlink($file);
                    return true;
                } catch (Exception $e) {
                    $this->error($e, $this->schemaName);
                    return false;
                }
            }
        }
    }
    public function findOne($id)
    {
        if (!$this->documentExist($id)) {
            throw new Exception("Document not found " . $this->schemaName . " (id=$id)");
            $this->error("Document not found", $this->schemaName . " (id=$id)");
            return false;
        }
        $document = json_decode(file_get_contents($this->schema . "/" . $id . ".json"), true);
        return $document;
    }
    public function update($query, $params)
    {
        $file     = false;
        $document = false;
        unset($params["id"]);
        if (is_numeric($query)) {
            if (!$this->documentExist($query)) {
                $this->error("Document not found", $this->schemaName . " (id=$query)");
                return false;
            }
            $file = $this->schema . "/" . $query . ".json";

            $document[] = json_decode(file_get_contents($file), true);
        } else {
            $document = $this->query($query);
        }
        if (!$document) {
            $this->error("Documents not found", $this->schemaName . " " . print_r($query, true));
            return false;
        }
        $return = array();
        foreach ($document as $d) {
            $params   = (!is_array($params)) ? json_decode($params, true) : $params;
            $return[] = $d = array_replace($d, $params);
            $file     = ($file) ? $file : $this->schema . "/" . $d["id"] . ".json";
            try {
                file_put_contents($file, json_encode($d));
            } catch (Exception $e) {
                $this->error($e, $this->schemaName);
                return false;
            }
        }
        return $return;
    }
}

class Result
{
    public function __construct($data)
    {
        $this->skip   = 0;
        $this->limit  = null;
        $this->result = $data;
        return $this;
    }
    public function limit($s, $l = null)
    {
        $limit = (!$l) ? $s : $l;
        $skip  = ($l) ? $s : 0;
        if ($limit > 1) {
            $this->result = array_slice($this->result, $skip, $limit);
        } else {
            $this->result = $this->result[0];
        }
        return $this;
    }
    public function execute($r)
    {
        $t = array();
        foreach ($this->result as $data) {
            $t[] = $r($data);
        }
        $this->result = $t;
        return $this;
    }
    public function random()
    {
        shuffle($this->result);
        return $this;
    }
    public function sort($p)
    {
        usort($this->result, $p);
        return $this;
    }
    public function result($index)
    {
        if ($index !== null && is_numeric($index)) {
            return $this->result[$index];
        }
        return $this->result;
    }
    public function reverse()
    {
        $this->result = array_reverse($this->result);
        return $this;
    }
    public function count()
    {
        if ($this->result) {
            return count($this->result);
        } else {
            return 0;
        }
    }
}
class UtopyaDB extends Query
{
    public function __construct($db)
    {
        $this->utopyaPath = realpath(dirname(__FILE__)) . "/";
        if (file_exists($this->utopyaPath . $db)) {
            $this->db     = $db;
            $this->dbPath = $this->utopyaPath . $db . "/";
            $this->updateSchemas();
            return $this;
        } else {
            $this->createDB($db);
        }
    }

    public function __get($schema)
    {
        return $this->schema($schema);
    }

    public function createDB($db)
    {
        mkdir($this->utopyaPath . $db);
        $this->db     = $db;
        $this->dbPath = $this->utopyaPath . $db . "/";
        $this->updateSchemas();
        return $this;
    }
}
