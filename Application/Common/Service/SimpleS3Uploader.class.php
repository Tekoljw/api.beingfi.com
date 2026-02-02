<?php
namespace Common\Service;

class SimpleS3Uploader {
    private $accessKey = 'AKIA3ISBWCMK7ISLLKNO';
    private $secretKey = '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';
    private $region = 'ap-northeast-3';
    private $bucket = 'bepay-files';
    
    public function __construct() {
    }
    
    /**
     * 生成 AWS 签名 v4
     */
    private function sign($key, $msg) {
        return hash_hmac('sha256', $msg, $key, true);
    }
    
    /**
     * 生成授权头
     */
    private function getAuthorizationHeader($method, $uri, $content = '') {
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        
        $canonicalUri = $uri;
        $canonicalQueryString = '';
        
        $canonicalHeaders = "host:{$this->bucket}.s3.{$this->region}.amazonaws.com\n";
        $canonicalHeaders .= "x-amz-content-sha256:" . hash('sha256', $content) . "\n";
        $canonicalHeaders .= "x-amz-date:{$amzDate}\n";
        
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        
        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n" . hash('sha256', $content);
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        $kDate = $this->sign('AWS4' . $this->secretKey, $dateStamp);
        $kRegion = $this->sign($kDate, $this->region);
        $kService = $this->sign($kRegion, 's3');
        $kSigning = $this->sign($kService, 'aws4_request');
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        return "{$algorithm} Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
    }
    
    /**
     * 上传文件到 S3
     */
    public function uploadFile($localFile, $s3Key) {
        if (!file_exists($localFile)) {
            return array('status' => false, 'message' => '文件不存在');
        }
        
        $fileContent = file_get_contents($localFile);
        $method = 'PUT';
        $uri = '/' . $s3Key;
        $amzDate = gmdate('Ymd\THis\Z');
        
        $authorization = $this->getAuthorizationHeader($method, $uri, $fileContent);
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$s3Key}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $authorization,
                'x-amz-content-sha256: ' . hash('sha256', $fileContent),
                'x-amz-date: ' . $amzDate,
                'Content-Type: ' . $this->getMimeType($localFile),
            ),
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return array(
                'status' => true,
                'url' => "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$s3Key}",
                'message' => '上传成功'
            );
        } else {
            return array(
                'status' => false,
                'message' => "上传失败，HTTP 状态码: {$httpCode}, 错误: {$error}"
            );
        }
    }
    
    /**
     * 获取文件 MIME 类型
     */
    private function getMimeType($file) {
        $mimeTypes = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        );
        
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }
        
        // 如果无法确定，使用通用类型
        return 'application/octet-stream';
    }
    
    /**
     * 上传表单文件
     */
    public function uploadFormFile($file, $prefix = 'uploads') {
        if (!$file || !isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return array('status' => false, 'message' => '没有有效的文件上传');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $extension;
        $s3Key = $prefix . '/otcPaymentVoucher/' . date('Y/m/d/') . $fileName;
        
        return $this->uploadFile($file['tmp_name'], $s3Key);
    }
}