<?php
class DataStore
{
    private $path;

    public function __construct($filename)
    {
        $this->path = __DIR__ . '/../data/' . $filename . '.json';
        if (!file_exists($this->path)) {
            file_put_contents($this->path, strpos($filename, 'state') !== false ? '{}' : '[]');
        }
    }

    public function read()
    {
        $data = file_get_contents($this->path);
        return json_decode($data, true) ?: (strpos($this->path, 'state') !== false ? [] : []);
    }

    public function write($data)
    {
        return file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function add($item)
    {
        $data = $this->read();
        $item['id'] = uniqid();
        $item['created_at'] = date('c');
        $data[] = $item;
        $this->write($data);
        return $item;
    }

    public function update($id, $newData)
    {
        $data = $this->read();
        foreach ($data as &$item) {
            if ($item['id'] === $id) {
                // merging allows partial updates
                $item = array_merge($item, $newData);
                $this->write($data);
                return $item;
            }
        }
        return false;
    }

    public function delete($id)
    {
        $data = $this->read();
        $filtered = array_filter($data, function ($item) use ($id) {
            return isset($item['id']) && $item['id'] !== $id;
        });
        if (count($data) !== count($filtered)) {
            $this->write(array_values($filtered));
            return true;
        }
        return false;
    }

    public function getById($id)
    {
        $data = $this->read();
        foreach ($data as $item) {
            if (isset($item['id']) && $item['id'] === $id) return $item;
        }
        return null;
    }
}

// Utility functions for API response
function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
