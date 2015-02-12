<?php

namespace Teknomavi\Tcmb;

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

    /**
     * CacheProvider için kullanılacak önbellek anahtarı
     *
     * @var string
     */
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
     *
     * @throws Exception\ConnectionFailed
     */
    private function getTcmbData()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://www.tcmb.gov.tr/kurlar/today.xml");
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception\ConnectionFailed("Sunucu Bağlantısı Kurulamadı: " . curl_error($ch));
        }
        curl_close($ch);
        $this->data = $this->formatTcmbData((array)simplexml_load_string($response));
        $timezone   = new \DateTimeZone('Europe/Istanbul');
        $now        = new \DateTime("now", $timezone);
        if ($this->data['today'] == $now->format("d.m.Y")) {
            $expire = "Tomorrow 15:30";
        } else {
            $expire = "Today 15:30";
        }
        $expireDate           = new \DateTime($expire, $timezone);
        $this->data['expire'] = $expireDate->getTimestamp();
        if (!is_null($this->cacheDriver)) {
            $lifetime = $expire - $now->getTimestamp();
            // Eğer dosyanın geçerlilik süresi bitmişse veriyi sadece 5 dakika önbellekte tutuyoruz.
            $this->cacheDriver->save($this->cacheKey, $this->data, $lifetime > 0 ? $lifetime : 300);
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
