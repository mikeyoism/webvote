<?php
include '../php/common.inc';
include 'RestController.php';
//some public rest api functions for usage in in for example excel (non-protected, staistics only)
class statisticsController extends RestController
{
    //http://localhost:65193/vote/api/index.php/statistics/getBeerCount?cid=93
    public function getBeerCountAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();

        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
         
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $beerCounts = array();
                if (isset($arrQueryStringParams['categoryid']) )
                {
                    
                    $beerCount = $dbAccess->getBeerCountForCategory($arrQueryStringParams['categoryid']);
                    $responseData = json_encode($beerCount);
                }
                else
                {
                    foreach ($categories as $category) {
                        $beerCount = $dbAccess->getBeerCountForCategory($category['id']);
                        array_push($beerCounts,$beerCount);
                    }
                    $responseData = json_encode($beerCounts);
                }
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }


    }
    //getRatingCount
    public function getRatingCountAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $voteCountStartTime = $openTimes['voteCountStartTime'];                
                $beerCounts = array();
                if (isset($arrQueryStringParams['categoryid']) )
                {
                    
                    $beerCount = (int)$dbAccess->getRatingCount($arrQueryStringParams['categoryid'],$voteCountStartTime);
                    $responseData = json_encode($beerCount);
                }
                else
                {
                    foreach ($categories as $category) {
                        $beerCount = (int)$dbAccess->getRatingCount($category['id'],$voteCountStartTime);
                        
                        array_push($beerCounts,$beerCount);
                    }
                    $responseData = json_encode($beerCounts);
                }
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //getVoteCodeCount per category
    public function getVoteCodeCountAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $voteCountStartTime = $openTimes['voteCountStartTime'];                
                $beerCounts = array();
                if (isset($arrQueryStringParams['categoryid']) )
                {
                    
                    $beerCount = (int)$dbAccess->getVoteCodeCount($arrQueryStringParams['categoryid'],$voteCountStartTime);
                    $responseData = json_encode($beerCount);
                }
                else
                {
                    foreach ($categories as $category) {
                        $beerCount = (int)$dbAccess->getVoteCodeCount($category['id'],$voteCountStartTime);
                        
                        array_push($beerCounts,$beerCount);
                    }
                    $responseData = json_encode($beerCounts);
                }
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //getDrankCheckCount per category
    public function getDrankCheckCountAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $voteCountStartTime = $openTimes['voteCountStartTime'];                
                $beerCounts = array();
                if (isset($arrQueryStringParams['categoryid']) )
                {
                    
                    $beerCount = (int)$dbAccess->getDrankCheckCount($arrQueryStringParams['categoryid'],$voteCountStartTime);
                    $responseData = json_encode($beerCount);
                }
                else
                {
                    foreach ($categories as $category) {
                        $beerCount = (int)$dbAccess->getDrankCheckCount($category['id'],$voteCountStartTime);
                        
                        array_push($beerCounts,$beerCount);
                    }
                    $responseData = json_encode($beerCounts);
                }
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //get BeerCountForCategory
    public function getBeerCountForCategoryAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $beerCounts = array();
                if (isset($arrQueryStringParams['categoryid']) )
                {
                    
                    $beerCount = $dbAccess->getBeerCountForCategory($arrQueryStringParams['categoryid']);
                    $responseData = json_encode($beerCount);
                }
                else
                {
                    foreach ($categories as $category) {
                        $beerCount = $dbAccess->getBeerCountForCategory($category['id']);
                        array_push($beerCounts,$beerCount);
                    }
                    $responseData = json_encode($beerCounts);
                }
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }

    //getCategory names
    public function getCategoryNamesAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $categoryNames = array();
                foreach ($categories as $category) {
                    $categoryName = $category['name'];
                    array_push($categoryNames,$categoryName);
                }
                $responseData = json_encode($categoryNames);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //getCategory descriptions
    public function getCategoryDescriptionsAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $categoryDescriptions = array();
                foreach ($categories as $category) {
                    $categoryDescription = $category['description'];
                    //as utf-8
                    $categoryDescription = mb_convert_encoding($categoryDescription, 'UTF-8', 'UTF-8');
                    
                    array_push($categoryDescriptions,$categoryDescription);
                }
                $responseData = json_encode($categoryDescriptions);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //getCategory ids
    public function getCategoryIdsAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $categoryIds = array();
                foreach ($categories as $category) {
                    $categoryId = $category['id'];
                    array_push($categoryIds,$categoryId);
                }
                $responseData = json_encode($categoryIds);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }
    
        // send output
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //get category count
    public function getCategoryCountAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $categories = $dbAccess->getCategories($competition['id']);
                $categoryCount = count($categories);
                $responseData = json_encode($categoryCount);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
    //get if competition is open
    public function getCompetitionOpenAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $competitionOpen = $openTimes['open'];
                $responseData = json_encode($competitionOpen);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', 'HTTP/1.1 500 Internal Server Error')
            );
        }
    }
    //get competition close time
    public function getCompetitionCloseTimeAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $competitionCloseTime = $openTimes['closeTime'];
                $responseData = json_encode($competitionCloseTime);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', 'HTTP/1.1 500 Internal Server Error')
            );
        }
    }
    //get competition close time, in hh:mm format
    public function getCompetitionClosesHhmmAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $competitionClosesHhmm = $openTimes['closeTime']->format('H:i');
                $responseData = json_encode($competitionClosesHhmm);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', 'HTTP/1.1 500 Internal Server Error')
            );
        }
    }
    //get openclose text
    public function getCompetitionStatusTextAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        $responseData = null;
        if (strtoupper($requestMethod) == 'GET') {
            try {

                
                $dbAccess = new DbAccess();
                //is cid (competition id) set in query string?
                //if so, get data for that competition
                //else get data for default configuration
                if (isset($arrQueryStringParams['cid'])) {
                    $competitionId = $arrQueryStringParams['cid'];
                } else {
                    $competitionId = getCompetitionId();
                }                
                $competition = $dbAccess->getCompetition($competitionId);
                $openTimes = $dbAccess->calcCompetitionTimes($competition);
                $competitionStatusText = $openTimes['openCloseText'];
                $responseData = json_encode($competitionStatusText);
            } catch (Exception $e) {
                $strErrorDesc = $e->getMessage();
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method Not Allowed';
            $strErrorHeader = 'HTTP/1.1 405 Method Not Allowed';
        }

        // send output 
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(
                json_encode(array('error' => $strErrorDesc)),
                array('Content-Type: application/json', 'HTTP/1.1 500 Internal Server Error')
            );
        }
    }

}