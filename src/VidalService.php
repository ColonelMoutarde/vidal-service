<?php
namespace Hagag\VidalService;

use Dompdf\Exception;
use GuzzleHttp;
use \Hagag\VidalService\Utilities\XmlHandler as XmlHandler;

class VidalService {
	private $appId;
	private $appKey;
	private $guzzleClient;
	private $xmlHandler;
	private $baseUrl = 'http://api-sa.vidal.fr/rest/api/';

	public function __construct($appId, $appKey) {
		$this->appId = $appId;
		$this->appKey = $appKey;
		$this->guzzleClient = new GuzzleHttp\Client();
		$this->xmlHandler = new XmlHandler();
	}

	public function index() {
		echo "VIDAL API Package";
	}

	/**
	 * @description :  Using green rain code to get vidal medication ID
	 * @param string $greenRainCode green rain code
	 * @return : Medication info array
	 */
	public function getMedicationByGreenRainCode($greenRainCode = null) {
		try {
			if ($greenRainCode == null) {
				throw new Exception('Greencode Missing');
			}
			$operation = 'search?';
			$medication = array();
			$response = $this->guzzleClient->get($this->baseUrl . $operation . 'code=' . $greenRainCode . '&app_id=' . $this->appId . '&app_key=' . $this->appKey);
			if ($response->getStatusCode() == 200) {
				$medicationResponseTags = $this->xmlHandler->toArray($response->getBody()->getContents());
				foreach ($medicationResponseTags as $medicationResponseTag) {
					$keys = array_keys($medicationResponseTag['ENTRY']);
					foreach ($keys as $key) {
						if (strpos($key, 'VIDAL') !== false) {
							$newKey = strtolower(str_replace("VIDAL:", "", $key));
							$medication[$newKey] = $medicationResponseTag['ENTRY'][$key];
						}
					}
				}
                return $medication;
			} else {
				return $response->getBody();
			}
		} catch (\Exception $e) {
			return $e;
		}
	}

    /**
     * @description :  Using name to get vidal medication ID
     * @param string name
     * @return : Medication info array
     */
    public function getMedicationByName($name = null) {
        try {
            if ($name == null) {
                throw new Exception('Name Missing');
            }
            $operation = 'pathologies?';
            $medication = array();
            $medications = array();
            $response = $this->guzzleClient->get($this->baseUrl . $operation . 'q=' . $name . '&app_id=' . $this->appId . '&app_key=' . $this->appKey);
            if ($response->getStatusCode() == 200) {
                $medicationResponseTags = $this->xmlHandler->toArray($response->getBody()->getContents());
                foreach ($medicationResponseTags as $medicationResponseTag) {
                    $keys = array_keys($medicationResponseTag['ENTRY']);
                    foreach ($keys as $key) {
                        if (strpos($key, 'VIDAL') !== false) {
                            $newKey = strtolower(str_replace("VIDAL:", "", $key));
                            $medication[$newKey] = $medicationResponseTag['ENTRY'][$key];
                        }
                        $medications[]=$medication;
                    }
                }
                return $medications;
            } else {
                return $response->getBody();
            }
        } catch (\Exception $e) {
            return $e;
        }
    }
	/**
	 * @description :  get vidal medication info by id
	 * @param : array : patient['date_of_birth'] required ,
     * patient['gender'] optional - Possible values:'MALE', 'FEMALE', 'UNKNOWN',
     * patient['weight'] optional default is 0,
     * patient['height'] optional default is 0,
     * patient['breastFeeding'] optional - Possible values:'NONE', 'LESS_THAN_ONE_MONTH', 'MORE_THAN_ONE_MONTH', 'ALL',
     * patient['creatin'] optional - Normal creatinine clearance is 120 ,
     * patient['hepaticInsufficiency'] optional - Possible values:'NONE', 'MODERATE', 'SEVERE',
     * @param :array : allergyClasses : names of Allergy classes by class
     * @param :array : allergyIngredients : names of Allergy classes by Ingredients
     * @param :array : pathologies : ICD10 Codes for pathologies
     * @param :array : medications : List of medications  names
     * @return : Medication info array
	 */
	public function getPatientAlerts($patient = [],$allergyClasses = [],$allergyIngredients = [], $pathologies = [],$medications = []) {
		try {
			if (empty($patient) && !key_exists('date_of_birth',$patient) || empty($medications)) {
				throw new \Exception('Parameters Missing (patient profile at least date_of_birth Or medications)');
			}
			$operation = 'alerts?';
            $patient['dateOfBirth'] = new \DateTime($patient['date_of_birth']);
            $patient['dateOfBirth'] = $patient['dateOfBirth']->format('Y-m-dTH:i:s.uZ');
            $allergyClassesIds=[];
            $allergyIngredientsIds=[];
            $pathologiesIds=[];
            $medicationsIds=[];
            foreach ($allergyClasses as $allergyClass){
                $allergyClass = $this->getAllergyByClassOrIngredients($allergyClass);
                $allergyClassesIds[]=$allergyClass['id'];
            }
            foreach ($allergyIngredients as $allergyIngredient){
                $allergyIngredient = $this->getAllergyByClassOrIngredients($allergyIngredient);
                $allergyIngredientsIds[]=$allergyIngredient['id'];
            }
            foreach ($pathologies as $pathology){
                $pathology = $this->getPathologyByICD10Code($pathology);
                $pathologiesIds[]=$pathology['id'];
            }
            foreach ($medications as $medication){
                $medication = $this->getMedicationByGreenRainCode($medication);
                $medicationsIds[]=$medication['id'];
            }
            $xmlRequest = $this->xmlHandler->createPrescriptionXml($patient,$allergyClassesIds,$allergyIngredientsIds,$pathologiesIds,$medicationsIds);
            $response = $this->guzzleClient->post(
                $this->baseUrl . $operation . 'app_id=' . $this->appId . '&app_key=' . $this->appKey,
                [
                 'headers'  => ['Content-Type' => 'text/xml'],
                 'body' => $xmlRequest
                ]
            );
            if ($response->getStatusCode() == 200) {
                return ($this->formatAlertResponse($this->xmlHandler->toArray($response->getBody()->getContents())));
            }else{
                throw new \Exception('Unknown Error Occurred');
            }
		} catch (\Exception $e) {
			return ($e);
		}
	}
    /**
     * @description :  format alert response
     * @param : array : alert array response of vidal API
     * @return : alert info array
     */
	private function formatAlertResponse($alert){
	    $formattedAlert = array();
        $formattedAlert['alert'] = $alert['FEED']['ENTRY'][0]['VIDAL:TYPE'];
        $formattedAlert['alertType'] = $alert['FEED']['ENTRY'][1]['VIDAL:ALERTTYPE']['content'];
        $formattedAlert['alertSeverity'] = $alert['FEED']['ENTRY'][1]['VIDAL:SEVERITY'];
        $formattedAlert['alertContent'] = $alert['FEED']['ENTRY'][1]['CONTENT']['content'];
        $formattedAlert['alertTitle'] = $alert['FEED']['ENTRY'][0]['TITLE'];
        return $formattedAlert;
    }
	/**
	 * @description :  get vidal medication info by id
	 * @param : string : id
	 * @return : Medication info array
	 */
	public function getMedicationById($id = null) {
		try {
			if ($id == null) {
				throw new Exception('id Missing');
			}
			$operation = 'package/';
			$medication = array();
			$response = $this->guzzleClient->get($this->baseUrl . $operation . $id . '?app_id=' . $this->appId . '&app_key=' . $this->appKey);
			if ($response->getStatusCode() == 200) {
				$medicationResponseTags = $this->xmlHandler->toArray($response->getBody()->getContents());
				foreach ($medicationResponseTags as $medicationResponseTag) {
					$keys = array_keys($medicationResponseTag['ENTRY']);
					foreach ($keys as $key) {
						if (strpos($key, 'VIDAL') !== false) {
							$newKey = strtolower(str_replace("VIDAL:", "", $key));
							$medication[$newKey] = $medicationResponseTag['ENTRY'][$key];
						}
					}
				}
			} else {
				return $response->getBody();
			}
		} catch (\Exception $e) {
			return $e;
		}
	}
	/**
	 * @description :  get vidal medication info by ICD10Code
	 * @param : string : id
	 * @return : Medication info array
	 */
	public function getPathologyByICD10Code($icd10Code = null) {
		try {
			if ($icd10Code == null) {
				throw new Exception('icd10Code Missing');
			}
			$operation = 'pathologies?';
			$medication = array();
			$response = $this->guzzleClient->get($this->baseUrl.$operation .'app_id='.$this->appId.'&app_key='.$this->appKey.'&filter=CIM10&code='.$icd10Code);
			if ($response->getStatusCode() == 200) {
				$medicationResponseTags = $this->xmlHandler->toArray($response->getBody()->getContents());
				foreach ($medicationResponseTags as $medicationResponseTag) {
					$keys = array_keys($medicationResponseTag['ENTRY']);
					foreach ($keys as $key) {
						if (strpos($key, 'VIDAL') !== false) {
							$newKey = strtolower(str_replace("VIDAL:", "", $key));
							$medication[$newKey] = $medicationResponseTag['ENTRY'][$key];
						}
					}
				}
				return $medication;
			} else {
				return $response->getBody();
			}
		} catch (\Exception $e) {
			return $e;
		}
	}
	/**
	 * @description :  Using allergy class or ingredients code to get vidal allergy
	 * @param : string : allergy class
	 * @return : Allergy info array
	 */
	public function getAllergyByClassOrIngredients($allergyClassIngredients = null) {
		try {
			if ($allergyClassIngredients == null) {
				throw new Exception('Allergy Class Missing');
			}
			$operation = 'allergies?';
			$allergy = array();
			$response = $this->guzzleClient->get($this->baseUrl . $operation . 'q=' . $allergyClassIngredients . '&app_id=' . $this->appId . '&app_key=' . $this->appKey);
			if ($response->getStatusCode() == 200) {
				$allergyResponseTags = $this->xmlHandler->toArray($response->getBody()->getContents());
				foreach ($allergyResponseTags as $allergyResponseTag) {
					$keys = array_keys($allergyResponseTag['ENTRY']);
					foreach ($keys as $key) {
						if (strpos($key, 'VIDAL') !== false) {
							$newKey = strtolower(str_replace("VIDAL:", "", $key));
							$allergy[$newKey] = $allergyResponseTag['ENTRY'][$key];
						}
					}
				}
				return $allergy;
			} else {
				return $response->getBody();
			}
		} catch (\Exception $e) {
			return $e;
		}
	}
}