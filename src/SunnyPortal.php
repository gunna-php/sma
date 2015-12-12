<?php
namespace GunnaPHP\SMA;

use Gunna\Helpers\JSON;

/**
* SMA Sunny Port Data Extractor
*
* Extracts live data from the sunny portal at a data resolution of up to 15sec
*
* LICENSE: TBA
*
* @category   GunnaPHP
* @package    SMS
* @subpackage SunnyPortal
* @copyright  Copyright (c) 2015 Steven Miles
* @version    0.1
* @link       http://guthub.com/GunnPHP/SMA
*/

class SunnyPortal
{
    
/**
 * Sunny Portal Domain
 * @var string 
 */  
    
  protected $domain =  'https://www.sunnyportal.com';

/**
 * Cookie Storage File Name
 * @var string 
 */  

  protected $cookieFilePath = 'sunnyportal.cookies';

/**
 * Currently selected plantID
 * @var string 
 */  

  protected $currentPlantID = '';



  protected $lastUpdate = null;

/**
 * Login Username
 * @var string 
 */  

  protected $username = '';

/**
 * Login Password
 * @var string 
 */  
 
  protected $password = '';

/**
 * Currently Logged into Portal
 * @var bool
 */  
 
  public    $isLoggedin = false;


/**
 * Post Params required by sunnyportal
 * @var array
 */  

  protected $postParams = [
    '__EVENTTARGET'                            => '',
    '__EVENTARGUMENT'                          => '',
    '__VIEWSTATE'                              => '',
    '__VIEWSTATEGENERATOR'                     => '',
    'ctl00$ContentPlaceHolder1$hiddenLanguage' => 'en-us'
  ];

/**
 * CURL Connection Handle
 * @var resource
 */ 
  protected $ch;

/**
 * Class Constructor 
 *
 * @param array $cfg Configration params for connecting to sunny portal
 */

  public function __construct($cfg=null) 
  {
    if (is_object($cfg) || is_array($cfg)) {
      foreach ($cfg AS $name=>$value) {
        switch ($name) {
          case 'username':
          case 'password':
          case 'cookieFilePath':
            $this->$name = (string)$value;
            break;
        }
      }
    }
    if (empty($this->username) || empty($this->password)) return false;
    $this->cookieFilePath = sys_get_temp_dir().$this->cookieFilePath;
    
    $this->buildCURL();
    $this->initSession();
  }
  
  protected function buildCURL() {
    $this->ch = curl_init();
    // basic curl options for all requests
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, ["Accept: */*","Connection: Keep-Alive"]);
    curl_setopt($this->ch, CURLOPT_HEADER,  0);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($this->ch, CURLOPT_BINARYTRANSFER,TRUE);
    curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36"); 
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1); 
    curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFilePath); 
    curl_setopt($this->ch, CURLOPT_COOKIEJAR,  $this->cookieFilePath); 
  }
  
  protected function initSession() 
  {

    // If we have a session cookie that is less that 2hrs old assume we are logged in
    if (is_file($this->cookieFilePath) && filectime($this->cookieFilePath) > strtotime('-2 hour')) 
    {
      $this->isLoggedin = true;
      return;
    }
    
    // Clear previous session cookie
    if (is_file($this->cookieFilePath)) unlink($this->cookieFilePath);

    
    $result = $this->get($this->domain.'/Plants');
    
    // Check if it returned the PV System List
    $this->isLoggedin = preg_match('/PV System List/',$result,$matches)?true:false;
    
    if (!$this->isLoggedin) $this->login();
    
    preg_match_all('<input type="hidden" name="([^"]+)".*value="([^"]+)">',$result,$matches);
    foreach ($matches[1] AS $i=>$field)
    {
      $this->postParams[$field] = $matches[2][$i];
    }

  }
  
  
  
  public function login() 
  {
    $result = $this->post(
      $this->domain.'/Templates/Start.aspx?logout=true',
      [
        'ctl00$ContentPlaceHolder1$Logincontrol1$txtUserName'      => $this->username,
        'ctl00$ContentPlaceHolder1$Logincontrol1$txtPassword'      => $this->password,
        'ctl00$ContentPlaceHolder1$Logincontrol1$LoginBtn'         => 'Login'
      ]
    );
    
    $this->isLoggedin = preg_match('/PV System List/',$result)?true:false;
    
    if (!$this->isLoggedin) throw new \Exception('Error Logging into Sunny Portal');
  }
  
  public function relogin()
  {
    $this->logout();
    $this->isLoggedin = false;
    $this->buildCURL();
    $this->initSession();
  }
  public function logout() 
  {
    if ($this->isLoggedin == false) return;
  
    $this->get($this->domain.'/Templates/Logout.aspx');
  
    curl_close($this->ch);
  
    $this->deleteCookies();
  
  }
  
  
  public function deleteCookies()
  {
    if (is_file($this->cookieFilePath)) unlink($this->cookieFilePath);
  }
  
  protected function get($url,$headers=null) 
  { 
    curl_setopt($this->ch, CURLOPT_URL,$url);
    curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
    curl_setopt($this->ch, CURLOPT_HEADER, 0);
    $curlHeaders = [
      'Accept: text/html,application/xhtml+xml,application/xml,application/json,application/csv;q=0.9,*/*;q=0.8',
      'Accept-Language: en-us,en;q=0.5',
      'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
      'Keep-Alive: 115',
      'Connection: keep-alive',
      'Origin:'.$this->domain,
      'Host:www.sunnyportal.com'
    ];
    
    if (is_array($headers)) $curlHeaders = array_merge($curlHeaders,$headers);
    curl_setopt($this->ch, CURLOPT_HTTPHEADER,$curlHeaders);
    $content = curl_exec($this->ch); 
    return $content;
  }
  
  protected function xhr($url,$headers=null) 
  {
    curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
    curl_setopt($this->ch, CURLOPT_URL,$url);
    curl_setopt($this->ch, CURLOPT_HEADER, 0);
    curl_setopt($this->ch, CURLOPT_HTTPHEADER,
      array_merge([
        'Accept: text/html,application/xhtml+xml,application/xml,application/json;q=0.9,*/*;q=0.8',
        'Accept-Language: en-us,en;q=0.5',
        'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
        'Keep-Alive: 115',
        'Connection: keep-alive',
        'X-Requested-With: XMLHttpRequest',
        'Origin:'.$this->domain,
        'Host:www.sunnyportal.com'
      ],$headers)
    );

    $content = curl_exec($this->ch); 
    return $content;
  }

  
  protected function post($url,$fields) 
  {
    // Configure CURl Request
    curl_setopt($this->ch, CURLOPT_URL, $url); 
    curl_setopt($this->ch, CURLOPT_POST, 1); 
    curl_setopt($this->ch, CURLOPT_HTTPHEADER,[
      'Origin: '.$this->domain,
      'Upgrade-Insecure-Requests: 1'
    ]);
    if (is_array($fields))
    {
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query(array_merge($this->postParams,$fields))); 
    } 
    else 
    {
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields);   
    }
    // Request Data
    $result = curl_exec($this->ch);  

    curl_setopt($this->ch, CURLOPT_URL, ''); 
    curl_setopt($this->ch, CURLOPT_POST, false); 
    curl_setopt($this->ch, CURLOPT_POSTFIELDS,null); 

    return $result;
  }

/**
 * Submit a JSON Request to sunny portal
 *
 * @param string $url Request URI
 * @param mixed $json JSON Payload
 * @return string Result from request
 */ 

  protected function json($url,$json) 
  {
    if ( ! is_string($json) ) $json = json_encode($json);
    
    // Configure CURl Request
    curl_setopt($this->ch, CURLOPT_URL, $url); 
    curl_setopt($this->ch, CURLOPT_POST, 1); 
    curl_setopt($this->ch, CURLOPT_HTTPHEADER,[
      'Content-Type: application/json',
      'Content-Length: ' . strlen($json),
      'Origin: '.$this->domain,
      'Upgrade-Insecure-Requests: 1'
    ]);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $json);   
    // Request Data
    $result = curl_exec($this->ch);  

    curl_setopt($this->ch, CURLOPT_URL, ''); 
    curl_setopt($this->ch, CURLOPT_POST, false); 
    curl_setopt($this->ch, CURLOPT_POSTFIELDS,null); 

    return $result;
  }


/**
 * Get List of Plants Associated with this login
 * @return array [ plantOID => plantName ]
 */  
  
  public function getPlantList()
  {
    // Download and decode data  
    $url = $this->domain.'/Plants/GetPlantList';
    $result = $this->xhr($url,['Referer:'.$this->domain.'/Plants']);
    $data = json_decode($result);

    // Process Plant Data into someting useable
    $plants = [];
    foreach ($data->aaData AS $plant) {
        
      $plants[$plant->PlantOid] = $plant->PlantName;

    }
    
    return $plants;
  }

/**
 * Set Currently Selected Plant ID
 *
 * @param string $plantID PlantOID from plant list
 * @return void
 */  

  public function setPlant($plantID)
  {
    $this->get($this->domain.'/RedirectToPlant/'.$plantID);
  }
  
/**
 * Get Current Reading Values from Sunnry Portal
 * @return object 
 */   
  public function liveData()
  {
    $url = $this->domain.'/homemanager?t='.(time()*1000);

    try 
    {
      $result = $this->get($url);      
      $data = json_decode($result);
      if ( ! empty($data->ErrorMessages) ) {
        throw new \Exception($data->ErrorMessages[0]);
      }
      return $data;
      
    } catch (\Exception $e)
    {
      $this->relogin();
      return $this->liveData();
    }
  }


/**
 * Trigger Sunny Portal to request updated data from the Home Energy Monitor 
 * @return void
 */  
  
  public function triggerUpdate()
  {
    // Trigger Update of Live Data
    $url = $this->domain.'/FixedPages/HoManEnergyRedesign.aspx/UpdateLiveData';
    $this->json($url,'{}');
    $this->lastUpdate = new \DateTime();
  }




  public function energyBalance($period='day',$date='today')
  {
    $dateTime = new \DateTime($date);
    $dateTime->setTime(0,0,0);
    $tabNumber = 2;
    $anchorTime =$dateTime->format('U');
    switch (strtolower($period))
    {
      case 'current': $tabNumber = 0; break;
      case 'day':     $tabNumber = 1; break;
      case 'month':   $tabNumber = 2; break;
      case 'total':   $tabNumber = 3; break;    
    }

    // Trigger Update of Live Data
    $this->triggerUpdate();
    
    // Get Curent Values
    $url = $this->domain.'/FixedPages/HoManEnergyRedesign.aspx/GetLegendWithValues';
    $result = $this->json($url,json_encode(['anchorTime'=>$anchorTime,'tabNumber'=>$tabNumber]));

    // Parse Response 
    $data = json_decode($result);
    $data = json_decode($data->d);
    $values =  [];
    foreach ($data AS $value) {
      $values[$value->Key] = $value->Value;
    }
    return $values;
  }



  
  public function energyBalanceHistory()
  {
    
    $this->post($this->domain.'/FixedPages/HoManLive.aspx',[
      '__EVENTTARGET' => 'ctl00$NavigationLeftMenuControl$1_3',
      '__EVENTARGUMENT' => '',
      '__VIEWSTATE' => '',
      'ctl00$HiddenPlantOID'=>$this->currentPlantID,
      'ctl00$_scrollPosHidden' => '',
      'ctl00$NavigationLeftMenuControl$hfDisableBrowserRecommendation' => '',
      'LeftMenuNode_0' => 0,
      'LeftMenuNode_1' => 1,
      'LeftMenuNode_2' => 1,
      'LeftMenuNode_2_0' => 0,
      'LeftMenuNode_2_1' => 0,
      'LeftMenuNode_3' => 0,
      'ctl00$ContentPlaceHolder1$SelfConsumption_Status1$hfHomanDeviceId'=>'',
      'ctl00$ContentPlaceHolder1$SelfConsumption_Status1$Selfconsumption_StateLiveserverUrl'=>'',
      'ctl00$ContentPlaceHolder1$FixPageWidth' => 720,
      'TabSwitchConfigFixPageHid' => 0,
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$HiddenFieldPageOID' => '2b9cc71f-6513-411c-936d-46857fb3850c',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$HiddenFieldPageName' => 'HoManLive',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$RblLiveStatusDesign' => 'v1',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$EMailRecipientHidden' => '',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$EMailSenderHidden' => '',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$NameSenderHidden' => '',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$MessageHidden' => '',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$EMailRecipientTextBox' => '',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$NameSenderTextBox' => '',
      'ctl00$ContentPlaceHolder1$FixPageConfiguration1$MessageTextBox' => ''
    ]);
    
    
    
    //$url = $this->domain.'/Templates/DownloadDiagram.aspx?down=homanEnergyRedesign&chartId=mainChart';
    $url = 'http://www.sunnyportal.com/Templates/DownloadDiagram.aspx?down=homanEnergyRedesign&chartId=mainChart';
  //  $url = $this->domain.'/Templates/DownloadDiagram.aspx?down=homanEnergyRedesign&chartId=mainChart';
    curl_setopt($this->ch, CURLOPT_VERBOSE,1);
    $result = $this->get($url);
    print_r($result);
  }
 
}