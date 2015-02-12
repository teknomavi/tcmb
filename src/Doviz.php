<?php

namespace Teknomavi\Tcmb;

use GuzzleHttp\Client as HttpClient;

class Doviz
{

    /* Kur İsimleri */
    public $names = array(
        "USD" => "ABD DOLARI",
        "AUD" => "AVUSTRALYA DOLARI",
        "DKK" => "DANİMARKA KRONU",
        "EUR" => "EURO",
        "GBP" => "İNGİLİZ STERLİNİ",
        "CHF" => "İSVİÇRE FRANGI",
        "SEK" => "İSVEÇ KRONU",
        "CAD" => "KANADA DOLARI",
        "KWD" => "KUVEYT DİNARI",
        "NOK" => "NORVEÇ KRONU",
        "SAR" => "SUUDİ ARABİSTAN RİYALİ",
        "JPY" => "JAPON YENİ",
        "BGN" => "BULGAR LEVASI",
        "RON" => "RUMEN LEYİ",
        "RUB" => "RUS RUBLESİ",
        "IRR" => "İRAN RİYALİ",
        "CNY" => "ÇİN YUANI",
        "PKR" => "PAKİSTAN RUPİSİ"
    );

    /* KurTipleri */
    const TYPE_ALIS = "ForexBuying";
    const TYPE_EFEKTIFALIS = "BanknoteBuying";
    const TYPE_SATIS = "ForexSelling";
    const TYPE_EFEKTIFSATIS = "BanknoteSelling";

    /**
     * Aktarılmak istemeyen kurlar
     *
     * @var array
     */
    private $ignoredCurrencies = array( "XDR" );

    /**
     * İşlenmiş datanın tutulduğu değişken
     *
     * @var array
     */
    private $data = null;

    /**
     * Datanın cachelenmesi için gerekli driver
     *
     * @var \Doctrine\Common\Cache\CacheProvider
     */
    private $cacheDriver = null;

    private $cacheKey = "Teknomavi_Tcmb_Doviz_Data";

    /**
     * @param \Doctrine\Common\Cache\CacheProvider $cacheDriver
     */
    function __construct($cacheDriver = null)
    {
        if (!is_null($cacheDriver) && class_exists('\Doctrine\Common\Cache\CacheProvider')) {
            if ($cacheDriver instanceof \Doctrine\Common\Cache\CacheProvider) {
                $this->cacheDriver = $cacheDriver;
                if ($this->cacheDriver->contains($this->cacheKey)) {
                    $this->data = $this->cacheDriver->fetch($this->cacheKey);
                }
            }
        }
    }

    /**
     * TCMB sitesi üzerinden XML'i okur.
     */
    private function getTcmbData()
    {
        $client     = new HttpClient();
        $response   = $client->get("http://www.tcmb.gov.tr/kurlar/today.xml");
        $this->data = $this->formatTcmbData((array)simplexml_load_string($response->getBody()));
        if (!is_null($this->cacheDriver)) {
            if ($this->data['today'] == date("d.m.Y")) {
                $expire = strtotime("Tomorrow 15:30");
            } else {
                $expire = strtotime("Today 15:30");
            }
            $this->data['expire'] = $expire;
            $lifetime             = $expire - time();
            $this->cacheDriver->save($this->cacheKey, $this->data, $lifetime > 0 ? $lifetime : 30 * 60);
        }
    }

    /**
     * TCMB sitesinden okunan XML'deki datayı işler ve temizler
     *
     * @param array $data
     *
     * @return array
     */
    private function formatTcmbData($data)
    {
        $currencies = array();
        if (isset($data['Currency']) && count($data['Currency'])) {
            foreach ($data['Currency'] as $currency) {
                $currency     = (array)$currency;
                $currencyCode = $currency["@attributes"]["CurrencyCode"];
                if (in_array($currencyCode, $this->ignoredCurrencies)) {
                    continue;
                }
                $currencies[$currencyCode] = array(
                    "CurrencyName"          => $currency["Isim"],
                    self::TYPE_ALIS         => $currency[self::TYPE_ALIS] / $currency['Unit'],
                    self::TYPE_EFEKTIFALIS  => $currency[self::TYPE_EFEKTIFALIS] / $currency['Unit'],
                    self::TYPE_SATIS        => $currency[self::TYPE_SATIS] / $currency['Unit'],
                    self::TYPE_EFEKTIFSATIS => $currency[self::TYPE_EFEKTIFSATIS] / $currency['Unit']
                );
            }
        }
        return array(
            'today'      => $data["@attributes"]["Tarih"],
            'currencies' => $currencies
        );
    }

    /**
     * Belirtilen kura ait alış fiyatını getirir.
     *
     * @param string $currency
     * @param string $type
     *
     * @return float
     * @throws Exception\UnknownCurrencyCode
     * @throws Exception\UnknownPriceType
     */
    public function kurAlis($currency, $type = self::TYPE_ALIS)
    {
        if (is_null($this->data)) {
            $this->getTcmbData();
        }
        if (!isset($this->data['currencies'][$currency])) {
            throw new Exception\UnknownCurrencyCode("Tanımlanmayan Kur: " . $currency);
        }
        switch ($type) {
            case self::TYPE_ALIS:
            case self::TYPE_EFEKTIFALIS:
                return (float)$this->data['currencies'][$currency][$type];
            default:
                throw new Exception\UnknownPriceType("Tanımlanamayan Kur Tipi: " . $type);
        }
    }

    /**
     * Belirtilen kura ait satış fiyatını getirir.
     *
     * @param string $currency
     * @param string $type
     *
     * @return float
     * @throws Exception\UnknownCurrencyCode
     * @throws Exception\UnknownPriceType
     */
    public function kurSatis($currency, $type = self::TYPE_SATIS)
    {
        if (is_null($this->data)) {
            $this->getTcmbData();
        }
        if (!isset($this->data['currencies'][$currency])) {
            throw new Exception\UnknownCurrencyCode("Tanımlanmayan Kur: " . $currency);
        }
        switch ($type) {
            case self::TYPE_SATIS:
            case self::TYPE_EFEKTIFSATIS:
                return (float)$this->data['currencies'][$currency][$type];
            default:
                throw new Exception\UnknownPriceType("Tanımlanamayan Kur Tipi: " . $type);
        }
    }

    /**
     * @return array
     */
    public function getData()
    {
        if (is_null($this->data)) {
            $this->getTcmbData();
        }
        return $this->data;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function setData($data)
    {
        if (isset($data['currencies'], $data['expire']) && $data['expire'] > time()) {
            $this->data = $data;
            return true;
        }
        return false;
    }
}
