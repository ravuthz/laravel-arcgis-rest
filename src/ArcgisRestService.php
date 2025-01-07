<?php

namespace Ravuthz\ArcgisRest;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ArcgisRestService
{
    protected Client $http;
    protected mixed $model;
    protected ?string $table = null;
    protected ?string $token = null;

    public string $url;
    public string $layerId;
    public string $portal;
    public string $service;
    private string $username;
    private string $password;

    public function __construct($layerId, $model = null, $url = null, $portal = null)
    {
        $this->http = new Client();

        $url = $url ?? env('ARCGIS_URL');
        $portal = $portal ?? env('ARCGIS_PORTAL');

        $arcgisUrl = str_replace(['/MapServer', '/FeatureServer'], '', $url);

        $this->portal = $portal;
        $this->layerId = "$layerId";
        $this->service = $arcgisUrl;
        $this->model = $model ? app($model) : null;
        $this->table = $this->model ?
            $this->model->getTable() :
            array_search($layerId, config('layers', []));
    }

    public function getTableName()
    {
        return $this->table;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function requestToken($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $res = $this->request(
            'POST',
            [
                'f' => 'json',
                'referer' => $this->service,
                'username' => $this->username,
                'password' => $this->password
            ],
            $this->portal . '/generateToken',
            true
        );

        return $res['token'] ?? null;
    }

    public function generateToken($username, $password)
    {
        // $this->token = Cache::remember(
        //     $username,
        //     now()->addSeconds(5),
        //     fn() => $this->requestToken($username, $password)
        // );

        $this->token = $this->requestToken($username, $password);

        return $this->token;
    }

    public function validateJsonResponse($res, $formatJson)
    {
        $json = [];

        if ($res && $res->getStatusCode() >= 200 || $res->getStatusCode() < 300) {
            $json = json_decode($res->getBody()->getContents(), true);

            if (!empty($json['error'])) {
                $err = $json['error'];
                $code = $err['code'] ?? 500;
                //                $message = !empty($err['details']) ? implode(' ', $err['details']) : $err['message'];
                $message = $err['details'] ?? $err['message'];
                $message = implode(' ', $message);
                abort($code, "Arcgis[$code]: $message");
            }
        }

        return $formatJson ? $json : $res;
    }

    public function handleResponse($json, $error)
    {
        $data = $json[0] ?? $json;

        if ($data && !empty($data['success'])) {
            return $data;
        }

        $code = $data['error']['code'] ?? 0;
        $message = "Arcgis[$code]: " . ($data['error']['description'] ?? $error);

        if ($code == 1011) {
            throw new NotFoundHttpException($message, null, $code);
        }

        throw new BadRequestHttpException($message, null, $code);
    }

    public function requestRaw($method, $url, $options = [], $formatJson = false)
    {
        $res = $this->http->requestAsync($method, $url, $options)->wait();
        return $this->validateJsonResponse($res, $formatJson);
    }

    public function request($method, $body, $url, $formatJson = false)
    {
        $payload = [
            'headers' => [
                'Accept' => 'application/json; charset=utf-8',
            ]
        ];

        $body['f'] = 'json';
        $body['token'] = $this->token;

        if ($method == 'GET') {
            $payload['query'] = $body;
        } else {
            $payload['form_params'] = $body;
        }

        return $this->requestRaw($method, $url, $payload, $formatJson);
    }

    public function postFeatures($url, $features)
    {
        if (empty($features)) {
            return null;
        }

        return $this->request('POST', [
            'features' => json_encode($features['features'] ?? $features)
        ], $url, true);
    }

    private function deleteByQuery($url, $query = [])
    {
        if (empty($query)) {
            return null;
        }

        if (!empty($query['objectIds'])) {
            //            $query['objectIds'] = implode(', ', array_unique($query['objectIds']));
            $query['objectIds'] = implode(', ', $query['objectIds']);
        }

        return $this->request('POST', $query, $url, true);
    }

    public function createFeatures(array $features)
    {
        $url = "{$this->service}/FeatureServer/{$this->layerId}/addFeatures";
        // https://esri.github.io/arcgis-rest-js/api/feature-layer/addFeatures/
        // https://developers.arcgis.com/rest/services-reference/enterprise/add-features/
        return $this->postFeatures($url, $features);
    }

    public function updateFeatures(array $features)
    {
        $url = "{$this->service}/FeatureServer/{$this->layerId}/updateFeatures";
        // https://esri.github.io/arcgis-rest-js/api/feature-layer/updateFeatures/
        // https://developers.arcgis.com/rest/services-reference/enterprise/update-features/
        return $this->postFeatures($url, $features);
    }

    public function deleteFeatures(array $query)
    {
        $url = "{$this->service}/FeatureServer/{$this->layerId}/deleteFeatures";
        // https://esri.github.io/arcgis-rest-js/api/feature-layer/deleteFeatures/
        // https://developers.arcgis.com/rest/services-reference/enterprise/delete-features/
        return $this->deleteByQuery($url, $query);
    }

    public function fetchFeatures(array $query, $method = 'GET')
    {
        if (empty($query)) {
            return [];
        }

        $url = "{$this->service}/FeatureServer/{$this->layerId}/query";
        // https://esri.github.io/arcgis-rest-js/api/feature-layer/queryFeatures/
        // https://developers.arcgis.com/rest/services-reference/enterprise/query-feature-service/
        if (!empty($query['objectIds'])) {
            $query['objectIds'] = implode(', ', $query['objectIds']);
        }

        if (empty($query['outFields'])) {
            $query['outFields'] = '*';
        }

        return $this->request($method, $query, $url, true);
    }

    public function fetchFeature($objectid)
    {
        $url = "{$this->service}/FeatureServer/{$this->layerId}/{$objectid}";
        // https://esri.github.io/arcgis-rest-js/api/feature-layer/getFeature/
        // https://developers.arcgis.com/rest/services-reference/enterprise/feature-feature-service/
        return $this->request('GET', [], $url, true);
    }

    public function exportMap($params)
    {
        $url = "{$this->service}/MapServer/export";
        // https://developers.arcgis.com/rest/services-reference/enterprise/export-map/
        $body = [
            'url' => $url,
            'bboxSR' => $params['bboxSR'] ?? 32648,
            'imageSR' => $params['imageSR'] ?? 32648,
            'format' => $params['format'] ?? 'svg',
            'transparent' => $params['transparent'] ?? true,
            'f' => 'json',
            ...$params
        ];
        return $this->request('POST', $body, $url . '/request', true);
    }

    public function debug($label, $data)
    {
        Log::debug($label, is_array($data) ? $data : [$data]);
    }

    public function filterOutAttributes($data): array
    {
        return !empty($data) ? array_map(fn($i) => $i['attributes'], $data) : [];
    }

    public function fetch($query = [], $onlyAttributes = false)
    {
        $result = $this->fetchFeatures($query);

        if ($onlyAttributes && !empty($result['features'])) {
            return $this->filterOutAttributes($result['features']);
        }

        return $result;
    }

    /**
     * Find and fetch multiple records by array of globalid
     * The $onlyAttributes to filter out the $result['features']['attributes']
     * @param array $globalIds
     * @param array $options
     * @param bool $onlyAttributes
     * @return array
     */
    public function fetchByGlobalIds(array $globalIds, array $options = [], bool $onlyAttributes = false): array
    {
        if (empty($globalIds)) {
            return [];
        }

        return $this->fetch([
            'where' => "globalid IN ('" . implode("', '", $globalIds) . "')",
            ...$options
        ], $onlyAttributes);
    }

    /**
     * Find and fetch multiple records by array of objectid
     * The $onlyAttributes to filter out the $result['features']['attributes']
     * @param array $objectIds
     * @param array $options
     * @param bool $onlyAttributes
     * @return array
     */
    public function fetchByObjectIds(array $objectIds, array $options = [], bool $onlyAttributes = false): array
    {
        if (empty($objectIds)) {
            return [];
        }

        return $this->fetch(['objectIds' => $objectIds, ...$options], $onlyAttributes);
    }

    public function fetchFirst($query, $onlyAttributes = false)
    {
        if (is_array($query)) {
            $res = $this->fetch($query, $onlyAttributes);
        }

        if (is_string($query)) {
            $globalId = $query;
            $res = $this->fetchByGlobalIds([$globalId], [], $onlyAttributes);
        }

        if (is_numeric($query)) {
            $objectId = intval($query);
            $res = $this->fetchByObjectIds([$objectId], [], $onlyAttributes);
        }

        return $onlyAttributes ? $res[0] : $res;
    }
}
