<?php

namespace Teknomavi\Tcmb;

use Teknomavi\Common\Wrapper\Curl;

class Doviz
{
    /* Kur İsimleri */
    public $names = [
        'USD' => 'ABD DOLARI',
        'AUD' => 'AVUSTRALYA DOLARI',
        'DKK' => 'DANİMARKA KRONU',
        'EUR' => 'EURO',
        'GBP' => 'İNGİLİZ STERLİNİ',
        'CHF' => 'İSVİÇRE FRANGI',
        'SEK' => 'İSVEÇ KRONU',
        'CAD' => 'KANADA DOLARI',
        'KWD' => 'KUVEYT DİNARI',
        'NOK' => 'NORVEÇ KRONU',
        'SAR' => 'SUUDİ ARABİSTAN RİYALİ',
        'JPY' => 'JAPON YENİ',
        'BGN' => 'BULGAR LEVASI',
        'RON' => 'RUMEN LEYİ',
        'RUB' => 'RUS RUBLESİ',
        'IRR' => 'İRAN RİYALİ',
        'CNY' => 'ÇİN YUANI',
        'PKR' => 'PAKİSTAN RUPİSİ',
    ];
    /* Kur Tipleri */
    const TYPE_ALIS = 'ForexBuying';
    const TYPE_EFEKTIFALIS = 'BanknoteBuying';
    const TYPE_SATIS = 'ForexSelling';
    const TYPE_EFEKTIFSATIS = 'BanknoteSelling';
    /**
     * Aktarılmak istemeyen kurlar.
     *
     * @var array
     */
    private $ignoredCurrencies = ['XDR'];
    /**
     * İşlenmiş datanın tutulduğu değişken.
     *
     * @var array
     */
    private $data = null;
    /**
     * Datanın cachelenmesi için gerekli driver.
     *
     * @var \Doctrine\Common\Cache\CacheProvider
     */
    private $cacheDriver = null;
    /**
     * CacheProvider için kullanılacak önbellek anahtarı.
     *
     * @var string
     */
    private $cacheKey = 'Teknomavi_Tcmb_Doviz_Data';

    /**
     * @param \Doctrine\Common\Cache\CacheProvider $cacheDriver
     */
    public function __construct($cacheDriver = null)
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
     * @param Curl $curl
     *
     * @throws Exception\ConnectionFailed
     */
    private function getTcmbData(Curl $curl = null)
    {
        if (is_null($curl)) {
            $curl = new Curl();
        }
        $curl->setOption(CURLOPT_URL, 'http://www.tcmb.gov.tr/kurlar/today.xml');
        $curl->setOption(CURLOPT_HEADER, 0);
        $curl->setOption(CURLOPT_RETURNTRANSFER, 1);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, 1);
        $response = $curl->exec();
        if ($response === false) {
            throw new Exception\ConnectionFailed('Sunucu Bağlantısı Kurulamadı: ' . $curl->error());
        }
        $curl->close();
        $this->data = $this->formatTcmbData((array)simplexml_load_string($response));
        $timezone = new \DateTimeZone('Europe/Istanbul');
        $now = new \DateTime('now', $timezone);
        $expire = $this->data['today'] == $now->format('d.m.Y') ? 'Tomorrow 15:30' : 'Today 15:30';
        $expireDate = new \DateTime($expire, $timezone);
        $this->data['expire'] = $expireDate->getTimestamp();
        if (!is_null($this->cacheDriver)) {
            $lifetime = $expire - $now->getTimestamp();
            // Eğer dosyanın geçerlilik süresi bitmişse veriyi sadece 5 dakika önbellekte tutuyoruz.
            $this->cacheDriver->save($this->cacheKey, $this->data, $lifetime > 0 ? $lifetime : 300);
        }
    }

    /**
     * TCMB sitesinden okunan XML'deki datayı işler ve temizler.
     *
     * @param array $data
     *
     * @return array
     */
    private function formatTcmbData($data)
    {
        $currencies = [];
        if (isset($data['Currency']) && count($data['Currency'])) {
            foreach ($data['Currency'] as $currency) {
                $currency = (array)$currency;
                $currencyCode = $currency['@attributes']['CurrencyCode'];
                if (in_array($currencyCode, $this->ignoredCurrencies)) {
                    $currencies[$currencyCode] = [
                        self::TYPE_ALIS => $currency[self::TYPE_ALIS] / $currency['Unit'],
                        self::TYPE_EFEKTIFALIS => $currency[self::TYPE_EFEKTIFALIS] / $currency['Unit'],
                        self::TYPE_SATIS => $currency[self::TYPE_SATIS] / $currency['Unit'],
                        self::TYPE_EFEKTIFSATIS => $currency[self::TYPE_EFEKTIFSATIS] / $currency['Unit'],
                    ];
                }
            }
        }

        return [
            'today' => $data['@attributes']['Tarih'],
            'currencies' => $currencies,
        ];
    }

    /**
     * Belirtilen kura ait alış fiyatını getirir.
     *
     * @param string $currency
     * @param string $type
     *
     * @throws Exception\UnknownCurrencyCode
     * @throws Exception\UnknownPriceType
     *
     * @return float
     */
    public function kurAlis($currency, $type = self::TYPE_ALIS)
    {
        return $this->getCurrencyExchangeRate($currency, $type);
    }

    /**
     * Belirtilen kura ait satış fiyatını getirir.
     *
     * @param string $currency
     * @param string $type
     *
     * @throws Exception\UnknownCurrencyCode
     * @throws Exception\UnknownPriceType
     *
     * @return float
     */
    public function kurSatis($currency, $type = self::TYPE_SATIS)
    {
        return $this->getCurrencyExchangeRate($currency, $type);
    }

    /**
     * Belirtilen kura ait fiyatı getirir.
     *
     * @param string $currency
     * @param string $type
     *
     * @throws Exception\UnknownCurrencyCode
     * @throws Exception\UnknownPriceType
     *
     * @return float
     */
    public function getCurrencyExchangeRate($currency, $type = self::TYPE_ALIS)
    {
        if (is_null($this->data)) {
            $this->getTcmbData();
        }
        if (!isset($this->data['currencies'][$currency])) {
            throw new Exception\UnknownCurrencyCode('Tanımlanmayan Kur: ' . $currency);
        }
        switch ($type) {
            case self::TYPE_ALIS:
            case self::TYPE_SATIS:
            case self::TYPE_EFEKTIFALIS:
            case self::TYPE_EFEKTIFSATIS:
                return (float)$this->data['currencies'][$currency][$type];
            default:
                throw new Exception\UnknownPriceType('Tanımlanamayan Kur Tipi: ' . $type);
        }
    }

    /**
     * @param Curl $curl
     *
     * @throws Exception\ConnectionFailed
     *
     * @return array
     */
    public function getData(Curl $curl = null)
    {
        if (is_null($this->data)) {
            $this->getTcmbData($curl);
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
