<?php
class Cache {
    private $cache_dir;
    private $default_expiry = 3600; // 1 hour default

    public function __construct($cache_dir = 'cache/') {
        $this->cache_dir = $cache_dir;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    public function get($key) {
        $filename = $this->cache_dir . md5($key);
        
        if (!file_exists($filename)) {
            return false;
        }

        $data = file_get_contents($filename);
        if ($data === false) {
            return false;
        }

        $data = unserialize($data);
        
        if ($data['expires'] < time()) {
            unlink($filename);
            return false;
        }

        return $data['value'];
    }

    public function set($key, $value, $expiry = null) {
        if ($expiry === null) {
            $expiry = $this->default_expiry;
        }

        $filename = $this->cache_dir . md5($key);
        $data = serialize([
            'value' => $value,
            'expires' => time() + $expiry
        ]);

        return file_put_contents($filename, $data);
    }

    public function delete($key) {
        $filename = $this->cache_dir . md5($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return true;
    }

    public function clear() {
        $files = glob($this->cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
}
