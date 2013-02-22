<?php

class JQScalable_Heroku implements JQScalable
{
    protected $apiKey;
    protected $appName;

    protected $herokuClient;

    public function __construct($apiKey, $appName)
    {
        $this->appName = $appName;
        $this->apiKey  = $apiKey;

        $this->herokuClient = new HerokuClient($this->apiKey);
    }

    /**
     * Heroku does crazy things with signals if you tell it to scale down while it's still processing a (recent) down-scale request.
     * It's better to allow a good 15 seconds before issuing mutliple downscales to prevent jobs from being KILLED before they can be 
     * gracefully cleaned up.
     */
    function minSecondsToProcessDownScale()
    {
        return 15;
    }

    function countCurrentWorkersForQueue($queue)
    {
        $ps = $this->herokuClient->ps($this->appName);
        $workerCount = 0;
        foreach ($ps as $psEntry) {
            list($type, $num) = explode('.', $psEntry['process']);
            if ($type === $queue) $workerCount++;
        }

        return $workerCount;
    }

    function setCurrentWorkersForQueue($numWorkers, $queue)
    {
        $this->herokuClient->psScale($this->appName, $queue, $numWorkers);
    }
}

class HerokuClient
{

  private $_apiKey = NULL;

  public function __construct($apiKey)
  {
    if (!$apiKey) throw new Exception("Expected an api key.");
    $this->_apiKey = $apiKey;
  }

  public function ps($app)
  {
    if (!$app) throw new Exception("Expected an app.");

    // Hit the heroku json api
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.heroku.com/apps/{$app}/ps");
    curl_setopt($ch, CURLOPT_USERPWD, ":{$this->_apiKey}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output   = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle errors
    if ($error) throw new Exception("Error running curl: {$error}.");
    if ($httpCode >= 400) throw new Exception("HTTP error: {$httpCode}.");

    // Otherwise we're good!
    return json_decode($output, true);
  }

  public function psScale($app, $type, $quantity)
  {
    if (!$app) throw new Exception("Expected an app.");
    if (!$type) throw new Exception("Expected a process type.");
    if (!is_int($quantity)) throw new Exception("Expected a quantity: " . var_export($quantity, true));

    // Hit the heroku json api
    $ch = curl_init( );
    curl_setopt($ch, CURLOPT_URL, "https://api.heroku.com/apps/{$app}/ps/scale");
    curl_setopt($ch, CURLOPT_USERPWD, ":{$this->_apiKey}");
    $data = array(
        'type' => $type,
        'qty'  => $quantity
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output   = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle errors
    if ($error) throw new Exception("Error running curl: {$error}.");
    if ($httpCode >= 400) throw new Exception("HTTP error: {$httpCode}. {$output}");

    // Otherwise we're good!
    return true;
  }

  public function psRestart($app, $ps)
  {
    if (!$app) throw new Exception("Expected an app.");
    if (!$ps) throw new Exception("Expected a process id or type.");

    if (strpos('.', $ps))
    {
      $restartBy = 'ps';
    }
    else
    {
      $restartBy = 'type';
    }

    // Hit the heroku json api
    $ch = curl_init( );
    curl_setopt($ch, CURLOPT_URL, "https://api.heroku.com/apps/{$app}/ps/restart");
    curl_setopt($ch, CURLOPT_USERPWD, ":{$this->_apiKey}");
    $data = array(
        $restartBy => $ps
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output   = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle errors
    if ($error) throw new Exception("Error running curl: {$error}.");
    if ($httpCode >= 400) throw new Exception("HTTP error: {$httpCode}. {$output}");

    print_r($data);
    print_r($output);

    // Otherwise we're good!
    return true;
  }
}
