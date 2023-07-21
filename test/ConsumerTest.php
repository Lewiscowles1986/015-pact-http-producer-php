<?php

namespace Braddle;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use PhpPact\Standalone\ProviderVerifier\Model\Source\Broker;
use PhpPact\Standalone\ProviderVerifier\Model\ConsumerVersionSelectors;
use PhpPact\Standalone\ProviderVerifier\Model\Config\PublishOptions;
use PhpPact\Standalone\ProviderVerifier\Model\Source\Url;
use PhpPact\Standalone\ProviderVerifier\Verifier;
use PhpPact\Standalone\ProviderVerifier\Model\VerifierConfig;

class ConsumerTest extends TestCase
{
    public function testPersonConsumer()
    {
        $config = new VerifierConfig();
        $config
            ->setLogLevel('DEBUG');
        $config
            ->getProviderInfo()
            ->setName("personProvider")
            ->setHost('localhost')
            ->setPort('8080')
            ->setScheme('http')
            ->setPath('/');

        if ($isCi = getenv('CI')) {
            $publishOptions = new PublishOptions();
            $publishOptions
                ->setProviderVersion(exec('git rev-parse --short HEAD'))
                ->setProviderBranch(exec('git rev-parse --abbrev-ref HEAD'));
            $config->setPublishOptions($publishOptions);
        }

        $broker = new Broker();
        $broker->setUsername(getenv('PACT_BROKER_USERNAME'));
        $broker->setPassword(getenv('PACT_BROKER_PASSWORD'));
        $broker->setUsername(getenv('PACT_BROKER_TOKEN'));
        $verifier = new Verifier($config);

        // 1. verify with a broker, but using a pact url to verify a specific pact
        // PACT_URL=http://localhost:9292/pacts/provider/personProvider/consumer/personConsumer/latest
        if ($pact_url = getenv('PACT_URL')) {
            $url = new Url();
            $url->setUrl(new Uri($pact_url));
            $verifier->addUrl($url);
        }
        // 2. verify files from local directory or file 
        //    results will not be published
        else if ($pactDir = getenv('PACT_DIR')) {
            // $verifier->addDirectory($pactDir);
            $verifier->addDirectory(__DIR__ . '/../pacts');
        } else if ($pactFile = getenv('PACT_FILE')) {
            // $verifier->addDirectory($pactFile);
            $verifier->addFile(__DIR__ . '/../pacts/personconsumer-personprovider.json');
        } else {
            // 2. verify with broker by fetching dynamic pacts (with consumer version selectors)
            // if you don't setConsumerVersionSelectors then it will fetch the latest pact for the named provider

            if ($pactBrokerBaseUrl = getenv('PACT_BROKER_BASE_URL')) {
                $broker->setUrl(new Uri($pactBrokerBaseUrl));
            } else {
                $broker->setUrl(new Uri('http://localhost:9292'));
            }
            // we need to set the provider branch here for PactBrokerWithDynamicConfiguration
            // as $publishOptions->setProviderBranch value set above isn't used.
            $broker->setProviderBranch(exec('git rev-parse --abbrev-ref HEAD'));
            // NOTE - this needs to be a boolean, not a string value, otherwise it doesn't pass through the selector.
            // Maybe a pact-php or pact-rust thing
            $selectors = (new ConsumerVersionSelectors())
                ->addSelector(' { "matchingBranch" : true } ');
            // ->addSelector('{"mainBranch":true}');
            $broker->setConsumerVersionSelectors($selectors);
            $broker->setEnablePending(true);
            $broker->setIncludeWipPactSince('2020-01-30');
            $verifier->addBroker($broker);
        }


        $verifyResult = $verifier->verify();

        $this->assertTrue($verifyResult);
    }
}
