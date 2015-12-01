<?php
namespace Craft;


class Varnishpurge_PurgeTask extends BaseTask
{
    private $_urls;
    private $_locale;

    public function getDescription()
    {
        return Craft::t('Purging Varnish cache');
    }

    public function getTotalSteps()
    {
        $urls = $this->getSettings()->urls;
        $this->_locale = $this->getSettings()->locale;

        $this->_urls = array();
        $this->_urls = array_chunk($urls, 20);

        return count($this->_urls);
    }

    public function runStep($step)
    {
        VarnishpurgePlugin::log('Varnish purge task run step: ' . $step, LogLevel::Info, craft()->varnishpurge->getSetting('varnishLogAll'));

        $servers = craft()->varnishpurge->getSetting('varnishUrl');

        if(!is_array($servers)) {
          $servers = array($servers);
        }

        foreach($servers as $server) {

          $batch = \Guzzle\Batch\BatchBuilder::factory()
            ->transferRequests(20)
            ->bufferExceptions()
            ->build();

          $client = new \Guzzle\Http\Client();
          $client->setDefaultOption('headers/Accept', '*/*');

          foreach ($this->_urls[$step] as $url) {

              $urlComponents = parse_url($url);
              $targetUrl = preg_replace('{/$}', '', $server) . $urlComponents['path'];

              VarnishpurgePlugin::log('Adding url to purge: ' . $targetUrl . ' with Host: ' . $urlComponents['host'], LogLevel::Info, craft()->varnishpurge->getSetting('varnishLogAll'));

              $request = $client->createRequest('PURGE', $targetUrl);
              $request->addHeader('Host', $urlComponents['host']);

              $batch->add($request);
          }

          $requests = $batch->flush();

          foreach ($batch->getExceptions() as $e) {
              VarnishpurgePlugin::log('An exception occurred: ' . $e->getMessage(), LogLevel::Error);
          }

          $batch->clearExceptions();
        }

        return true;
    }

    protected function defineSettings()
    {
        return array(
          'urls' => AttributeType::Mixed,
          'locale' => AttributeType::String
        );
    }

}
