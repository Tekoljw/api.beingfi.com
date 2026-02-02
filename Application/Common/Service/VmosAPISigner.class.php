<?php
namespace Common\Service;

class VmosAPISigner
{
  private $accessKeyId;
  private $secretAccessKey;
  private $contentType = "application/json;charset=UTF-8";
  private $host = "api.vmoscloud.com";
  private $service = "armcloud-paas";
  private $algorithm = "HMAC-SHA256";

  // Constructor for VmosAPISigner
  public function __construct($accessKeyId, $secretAccessKey)
  {
    $this->accessKeyId = $accessKeyId;
    $this->secretAccessKey = $secretAccessKey;
  }

  // Generate authentication headers for API requests
  public function signRequest($method, $path, $queryParams = [], $body = null)
  {
    $params = "";
    if (strtoupper($method) === "POST" && $body !== null) {
      $params = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
    } elseif (strtoupper($method) === "GET" && !empty($queryParams)) {
      $params = http_build_query($queryParams);
    }

    $xDate = gmdate("Ymd\THis\Z");
    $shortXDate = substr($xDate, 0, 8);
    $credentialScope = "$shortXDate/{$this->service}/request";

    $xContentSha256 = hash("sha256", $params);

    $canonicalString = implode("\n", [
      "host:{$this->host}",
      "x-date:$xDate",
      "content-type:{$this->contentType}",
      "signedHeaders:content-type;host;x-content-sha256;x-date",
      "x-content-sha256:$xContentSha256"
    ]);

    $hashedCanonicalString = hash("sha256", $canonicalString);

    $stringToSign = implode("\n", [
      $this->algorithm,
      $xDate,
      $credentialScope,
      $hashedCanonicalString
    ]);

    $kDate = hash_hmac("sha256", $shortXDate, $this->secretAccessKey, true);
    $kService = hash_hmac("sha256", $this->service, $kDate, true);
    $signKey = hash_hmac("sha256", "request", $kService, true);

    $signature = hash_hmac("sha256", $stringToSign, $signKey);

    $authorization = implode(", ", [
      "{$this->algorithm} Credential={$this->accessKeyId}/$credentialScope",
      "SignedHeaders=content-type;host;x-content-sha256;x-date",
      "Signature=$signature"
    ]);

    return [
      "x-date: $xDate",
      "x-host: {$this->host}",
      "authorization: $authorization",
      "content-type: {$this->contentType}"
    ];
  }
}