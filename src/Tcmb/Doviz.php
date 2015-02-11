<?php

namespace Teknomavi\Tcmb;

use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Client as HttpClient;

class Doviz
{

    /* Kurlar */
    const CURRENCY_USD = "USD"; //ABD DOLARI
    const CURRENCY_AUD = "AUD"; //AVUSTRALYA DOLARI
    const CURRENCY_DKK = "DKK"; //DANİMARKA KRONU
    const CURRENCY_EUR = "EUR"; //EURO
    const CURRENCY_GBP = "GBP"; //İNGİLİZ STERLİNİ
    const CURRENCY_CHF = "CHF"; //İSVİÇRE FRANGI
    const CURRENCY_SEK = "SEK"; //İSVEÇ KRONU
    const CURRENCY_CAD = "CAD"; //KANADA DOLARI
    const CURRENCY_KWD = "KWD"; //KUVEYT DİNARI
    const CURRENCY_NOK = "NOK"; //NORVEÇ KRONU
    const CURRENCY_SAR = "SAR"; //SUUDİ ARABİSTAN RİYALİ
    const CURRENCY_JPY = "JPY"; //JAPON YENİ
    const CURRENCY_BGN = "BGN"; //BULGAR LEVASI
    const CURRENCY_RON = "RON"; //RUMEN LEYİ
    const CURRENCY_RUB = "RUB"; //RUS RUBLESİ
    const CURRENCY_IRR = "IRR"; //İRAN RİYALİ
    const CURRENCY_CNY = "CNY"; //ÇİN YUANI
    const CURRENCY_PKR = "PKR"; //PAKİSTAN RUPİSİ
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
     * @var CacheProvider
     */
    private $cacheDriver = null;

    private $cacheKey = "Teknomavi_Tcmb_Doviz_Data";

    /**
     * @param CacheProvider $cacheDriver
     */
    function __construct(CacheProvider $cacheDriver = null)
    {
        if (!is_null($cacheDriver)) {
            $this->cacheDriver = $cacheDriver;
            if ($this->cacheDriver->contains($this->cacheKey)) {
                $this->data = $this->cacheDriver->fetch($this->cacheKey);
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
        if (!isset($this->data[$currency])) {
            throw new Exception\UnknownCurrencyCode("Tanımlanmayan Kur: " . $currency);
        }
        switch ($type) {
            case self::TYPE_ALIS:
            case self::TYPE_EFEKTIFALIS:
                return (float)$this->data[$currency][$type];
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
        if (!isset($this->data[$currency])) {
            throw new Exception\UnknownCurrencyCode("Tanımlanmayan Kur: " . $currency);
        }
        switch ($type) {
            case self::TYPE_SATIS:
            case self::TYPE_EFEKTIFSATIS:
                return (float)$this->data[$currency][$type];
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
