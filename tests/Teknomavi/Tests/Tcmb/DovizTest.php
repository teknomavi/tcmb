<?php
namespace Teknomavi\Tests\Tcmb;

use Teknomavi\Tcmb\Doviz;

class DovizTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers \Teknomavi\Tcmb\Doviz::__construct
     * @covers \Teknomavi\Tcmb\Doviz::kurAlis
     * @covers \Teknomavi\Tcmb\Doviz::getCurrencyExchangeRate
     */
    public function testCacheProvider()
    {
        $cacheMock = $this->getMock('\Doctrine\Common\Cache\CacheProvider', ['contains', 'fetch', 'doFetch', 'doContains', 'doSave', 'doDelete', 'doFlush', 'doGetStats']);
        $cacheMock->expects($this->once())
            ->method('contains')
            ->will($this->returnValue(true));
        $cacheMock->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue([
                'currencies' => [
                    'USD' => [
                        Doviz::TYPE_ALIS         => 2.2,
                        Doviz::TYPE_EFEKTIFALIS  => 2.3,
                        Doviz::TYPE_SATIS        => 2.4,
                        Doviz::TYPE_EFEKTIFSATIS => 2.5,
                    ],
                ],
                'expire' => strtotime('+1 hour'),
            ]));
        $doviz = new Doviz($cacheMock);
        $this->assertEquals($doviz->kurAlis('USD'), 2.2);
    }

    /**
     * @covers \Teknomavi\Tcmb\Doviz::__construct
     * @covers \Teknomavi\Tcmb\Doviz::getData
     * @covers \Teknomavi\Tcmb\Doviz::getTcmbData
     * @expectedException \Teknomavi\Tcmb\Exception\ConnectionFailed
     */
    public function testConnectionFailed()
    {
        $curlMock = $this->getMock('\Teknomavi\Common\Wrapper\Curl', ['setOption', 'exec', 'error']);
        $curlMock->expects($this->any())
            ->method('setOption')
            ->will($this->returnValue(true));
        $curlMock->expects($this->once())
            ->method('exec')
            ->will($this->returnValue(false));
        $curlMock->expects($this->once())
            ->method('error')
            ->will($this->returnValue('Test Suite Sample Error'));
        $doviz = new Doviz();
        $data = $doviz->getData($curlMock);
        $this->assertTrue(isset($data['currencies']['USD']));
    }

    /**
     * @covers \Teknomavi\Tcmb\Doviz::__construct
     * @covers \Teknomavi\Tcmb\Doviz::getData
     * @covers \Teknomavi\Tcmb\Doviz::getTcmbData
     * @covers \Teknomavi\Tcmb\Doviz::formatTcmbData
     *
     * @uses   \Teknomavi\Tcmb\Doviz::getTcmbData
     */
    public function testGetData()
    {
        $cacheMock = $this->getMock('\Doctrine\Common\Cache\CacheProvider', ['contains', 'save', 'doFetch', 'doContains', 'doSave', 'doDelete', 'doFlush', 'doGetStats']);
        $cacheMock->expects($this->once())
            ->method('contains')
            ->will($this->returnValue(false));
        $cacheMock->expects($this->once())
            ->method('save')
            ->will($this->returnValue(true));
        $doviz = new Doviz($cacheMock);
        $data = $doviz->getData();
        $this->assertTrue(isset($data['currencies']['USD']));
    }

    /**
     * @covers \Teknomavi\Tcmb\Doviz::setData
     */
    public function testSetData()
    {
        $data = [
            'currencies' => [
                'USD' => [
                    Doviz::TYPE_ALIS         => 2.2,
                    Doviz::TYPE_EFEKTIFALIS  => 2.3,
                    Doviz::TYPE_SATIS        => 2.4,
                    Doviz::TYPE_EFEKTIFSATIS => 2.5,
                ],
            ],
            'expire' => strtotime('+1 hour'),
        ];
        $doviz = new Doviz();
        $this->assertTrue($doviz->setData($data));
        $this->assertFalse($doviz->setData([]));
    }

    /**
     * @covers \Teknomavi\Tcmb\Doviz::getCurrencyExchangeRate
     *
     * @uses   \Teknomavi\Tcmb\Doviz::getData
     * @expectedException \Teknomavi\Tcmb\Exception\UnknownCurrencyCode
     */
    public function testUnknownCurrencyCode()
    {
        $doviz = new Doviz();
        $doviz->getCurrencyExchangeRate('TST');
    }

    /**
     * @covers \Teknomavi\Tcmb\Doviz::getCurrencyExchangeRate
     *
     * @uses   \Teknomavi\Tcmb\Doviz::getData
     * @expectedException \Teknomavi\Tcmb\Exception\UnknownPriceType
     */
    public function testUnknownPriceType()
    {
        $doviz = new Doviz();
        $doviz->getCurrencyExchangeRate('USD', 'FAIL');
    }

    /**
     * @covers \Teknomavi\Tcmb\Doviz::getCurrencyExchangeRate
     * @covers \Teknomavi\Tcmb\Doviz::kurAlis
     * @covers \Teknomavi\Tcmb\Doviz::kurSatis
     *
     * @uses   \Teknomavi\Tcmb\Doviz::getData
     */
    public function testGetCurrencyExchangeRate()
    {
        $doviz = new Doviz();
        $data = $doviz->getData();
        $this->assertEquals($data['currencies']['USD'][Doviz::TYPE_ALIS], $doviz->kurAlis('USD'));
        $this->assertEquals($data['currencies']['USD'][Doviz::TYPE_SATIS], $doviz->kurSatis('USD'));
    }
}
