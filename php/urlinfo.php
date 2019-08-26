/*
 * Copyright 2019 Amazon.com, Inc. and its affiliates. All Rights Reserved.
 *
 * Licensed under the MIT License. See the LICENSE accompanying this file
 * for the specific language governing permissions and limitations under
 * the License.
 */

<?php
/**
 * Makes a request to AWIS for site info.
 */
require './aws.phar';

class UrlInfo {

    protected static $ActionName            = 'UrlInfo';
    protected static $ResponseGroupName     = 'Rank,LinksInCount';
    protected static $ServiceHost           = 'awis.stage.api.alexa.com';
    protected static $NumReturn             = 10;
    protected static $StartNum              = 1;
    protected static $ServiceURI            = "/api";

    public function UrlInfo($apiUser, $apiKey,  $site) {
        $now = time();
        $this->site = $site;
        $this->apiKey = $apiKey;
    }

    /**
     * Get site info from AWIS.
     */
    public function getUrlInfo() {
        self::GetCredentials();

        $canonicalQuery = $this->buildQueryParams();

        $url = 'https://' . self::$ServiceHost . self::$ServiceURI . '?' . $canonicalQuery;
        $ret = self::makeRequest($url, $authorizationHeader);
        echo "\nResults for " . $this->site .":\n\n";
        echo $ret;
    }

    /**
     * Builds query parameters for the request to AWIS.
     * Parameter names will be in alphabetical order and
     * parameter values will be urlencoded per RFC 3986.
     * @return String query parameters for the request
     */
    protected function buildQueryParams() {
        $params = array(
            'Action'            => self::$ActionName,
            'Count'             => self::$NumReturn,
            'ResponseGroup'     => self::$ResponseGroupName,
            'Start'             => self::$StartNum,
            'Url'               => $this->site
        );
        ksort($params);
        $keyvalue = array();
        foreach($params as $k => $v) {
            $keyvalue[] = $k . '=' . rawurlencode($v);
        }
        return implode('&',$keyvalue);
    }

    /**
     * Makes request to AWIS
     * @param String $url   URL to make request to
     * @param String authorizationHeader  Authorization string
     * @return String       Result of request
     */
    protected function makeRequest($url, $authorizationHeader) {
        echo "\nMaking request to:\n$url\n";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Accept: application/xml',
          'Content-Type: application/xml',
          'x-api-key: ' . $this->apiKey
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * Parses XML response from AWIS and displays selected data
     * @param String $response    xml response from AWIS
     */
    public static function parseResponse($response) {
        $xml = new SimpleXMLElement($response,LIBXML_ERR_ERROR,false,'http://awis.amazonaws.com/doc/2005-07-11');
        if($xml->count() && $xml->Response->UrlInfoResult->Alexa->count()) {
            $info = $xml->Response->UrlInfoResult->Alexa;
            $nice_array = array(
                'Links In Count' => $info->ContentData->LinksInCount,
                'Rank'           => $info->TrafficData->Rank
            );
        }
        foreach($nice_array as $k => $v) {
            echo $k . ': ' . $v ."\n";
        }
    }

}

if (count($argv) < 3) {
    echo "Usage: $argv[0] API_KEY SITE\n";
    exit(-1);
}
else {
    $apiKey = $argv[1];
    $site = $argv[2];
}

$urlInfo = new UrlInfo($apiUser, $apiKey, $site);
$urlInfo->getUrlInfo();

?>
