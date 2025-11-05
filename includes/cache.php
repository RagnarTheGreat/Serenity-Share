<?php
class FileCache {
    private $cache_dir;
    private $cache_time;
    
    public function __construct($cache_dir = 'cache', $cache_time = 3600) {
        $this->cache_dir = $cache_dir;
        $this->cache_time = $cache_time;
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
    }
    
    public function get($key) {
        $filename = $this->cache_dir . '/' . md5($key);
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($filename));
        
        if (!$data || !isset($data['time']) || !isset($data['value'])) {
            return false;
        }
        
        if (time() - $data['time'] > $this->cache_time) {
            unlink($filename);
            return false;
        }
        
        return $data['value'];
    }
    
    public function set($key, $value) {
        $filename = $this->cache_dir . '/' . md5($key);
        $data = [
            'time' => time(),
            'value' => $value
        ];
        
        return file_put_contents($filename, serialize($data));
    }
    
    public function clear() {
        $files = glob($this->cache_dir . '/*');
        foreach($files as $file) {
            if(is_file($file)) {
                unlink($file);
            }
        }
    }
}
