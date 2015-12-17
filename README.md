# T.C. Merkez Bankası Kur Kütüphanesi
## Teknomavi\Tcmb Nedir ?
T.C. Merkez Bankası tarafından http://www.tcmb.gov.tr/kurlar/today.xml adresinde yayınlanan güncel döviz kurlarını okumak için kullanılan açık kaynak bir PHP kütüphanesidir.

## Neler Yapılabilir ?
Bu kütüphane ile günlük döviz kurları otomatik olarak çekilmektedir. 
TCMB sitesinde yayınlanan tüm kurlar için mevcut "Alış", "Satış", "Efektif Alış" ve "Efektif Satış" değerlerine ulaşabilirsiniz. 

## Nasıl Kullanılır ?
Teknomavi\Tcmb composer ile kurulabilir. 
Projenizdeki composer.json dosyasında require bölümüne *"teknomavi/tcmb": "dev-master"* eklemeniz ve *composer update* komutunu çalıştırmanız yeterlidir. 

composer kurulumu/kullanımı hakkında bilgiye ihtiyacınız varsa [bu bağlantıdaki](http://www.teknomavi.com/yazilim/php/composer-paket-yoneticisi-nedir-nasil-kurulur-nasil-kullanilir/) dökümanı incelebilirsiniz.

### Örnek Kod
Kütüphanenin en temel kullanımı aşağıdaki gibidir;
```php
use Teknomavi\Tcmb\Doviz;
$doviz = new Doviz();
echo " USD Alış:" . $doviz->kurAlis("USD");
echo " USD Satış:" . $doviz->kurSatis("USD");
echo " EURO Efektif Alış:" . $doviz->kurAlis("EUR", Doviz::TYPE_EFEKTIFALIS);
echo " EURO Efektif Satış:" . $doviz->kurSatis("EUR", Doviz::TYPE_EFEKTIFSATIS);

```

## Sıkça Sorulan Sorular
### Kütüphanenin Her Seferinde TCMB Üzerinden Data Çekmesi Nasıl Engellenir?
TCMB Sitesinden çekilen veriler, sınıfı oluştururken vereceğiniz bir Doctrine\Common\Cache\CacheProvider üzerinde tutulabilir. 
Bu sayede her seferinde tcmb sitesinden çekilmeyeceği için performans artışı sağlanabilir.
Doctrine Cache hakkında detaylı bilgiye [buradan](http://doctrine-orm.readthedocs.org/en/latest/reference/caching.html) ulaşabilirsiniz.

Örnek: Doctrine Memcache CacheProvider ile kullanımı
```php
use Teknomavi\Tcmb\Doviz;

// Doctrine Memcache Init
$memcache = new Memcache();
$memcache->connect('localhost', 11211);
$cacheDriver = new \Doctrine\Common\Cache\MemcacheCache();
$cacheDriver->setMemcache($memcache);
// Doviz Kütüphanesi
$doviz = new Doviz($cacheDriver);
echo " USD Alış:" . $doviz->kurAlis("USD");
echo " USD Satış:" . $doviz->kurSatis("USD");
echo " EURO Efektif Alış:" . $doviz->kurAlis("EUR", Doviz::TYPE_EFEKTIFALIS);
echo " EURO Efektif Satış:" . $doviz->kurSatis("EUR", Doviz::TYPE_EFEKTIFSATIS);

```

### Doctrine\Common\Cache\CacheProvider harici bir önbellek yapısı kullanıyorum. Ne yapabilirim?
Sınıfın oluşturduğu data değişkenini getData() fonksiyonu kendiniz saklayıp, tekrar kullanacağınızda setData($data) fonksiyonu ile sınıfa tekrar verebilirsiniz.
 
Örnek: json_encode/json_decode ile önbelleğin bir dosyada tutulması
```php
$doviz = new \Teknomavi\Tcmb\Doviz();
// Cache Kodları Başlangıç
$fileName = dirname(__FILE__) . "/data.json";
if (file_exists($fileName)) {
    $data       = json_decode(file_get_contents($fileName), true);
    $cacheValid = $doviz->setData($data);
} else {
    $cacheValid = false;
}
if (!$cacheValid) {
    file_put_contents($fileName, json_encode($doviz->getData()));
}
// Cache Kodları Bitiş
echo " USD Alış:" . $doviz->kurAlis("USD");
echo " USD Satış:" . $doviz->kurSatis("USD");
echo " EURO Efektif Alış:" . $doviz->kurAlis("EUR", \Teknomavi\Tcmb\Doviz::TYPE_EFEKTIFALIS);
echo " EURO Efektif Satış:" . $doviz->kurSatis("EUR", \Teknomavi\Tcmb\Doviz::TYPE_EFEKTIFSATIS);

```
