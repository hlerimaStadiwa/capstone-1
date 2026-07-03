<?php
class SupabaseStorage {
    private $url;
    private $key;
    private $bucket;

    public function __construct() {
        $this->url = rtrim(getenv('SUPABASE_URL'), '/');
        $this->key = getenv('SUPABASE_KEY');
        $this->bucket = getenv('SUPABASE_BUCKET') ?: 'assignments'; // Default bucket
    }

    public function uploadFile($filePath, $fileData, $mimeType) {
        $endpoint = $this->url . '/storage/v1/object/' . $this->bucket . '/' . $filePath;
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->key,
            'Content-Type: ' . $mimeType,
            'apikey: ' . $this->key
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $this->getPublicUrl($filePath);
        }
        
        throw new Exception("Failed to upload file to Supabase. HTTP Code: $httpCode. Response: $response");
    }

    public function getPublicUrl($filePath) {
        return $this->url . '/storage/v1/object/public/' . $this->bucket . '/' . $filePath;
    }
}
