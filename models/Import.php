<?php
/*
    
 */
class Import
{ 
    const MAPPINGS = "mappings";

    public static function parsing($post) {
        $params = array("result"=>false);
        if($post['typeFile'] == "json" || $post['typeFile'] == "js" || $post['typeFile'] == "geojson")
            $params = self::parsingJSON($post);
        else if($post['typeFile'] == "csv")
            $params = self::parsingCSV($post); 
        return $params ;
    }

    public static function parsingCSV($post) {
        
        $attributesElt = ArrayHelper::getAllPathJson(file_get_contents("../../modules/communecter/data/import/".Element::getControlerByCollection($post["typeElement"]).".json", FILE_USE_INCLUDE_PATH));
        if($post['idMapping'] != "-1"){
            $where = array("_id" => new MongoId($post['idMapping']));
            $fields = array("fields");
            $mapping = self::getMappings($where, $fields);
            $arrayMapping = $mapping[$post['idMapping']]["fields"];
        }
        else
            $arrayMapping = array();

        $params = array("result"=>true,
                        "attributesElt"=>$attributesElt,
                        "arrayMapping"=>$arrayMapping,
                        "typeFile"=>$post['typeFile']);
        return $params ;
    }

    public static function parsingJSON($post){
        header('Content-Type: text/html; charset=UTF-8');
        $params = array("result"=>false);
        if(isset($post['file'])) {
            
            $json = $post['file'][0];
            //(empty($post["path"]) ? $post['file'][0] : $post['file'][0][$post["path"]]);
            // if (isset($post['path'])) {
            //     $json = $post['file'][0][$post['path']][0];
            // } 

            if($post['idMapping'] != "-1"){
                $where = array("_id" => new MongoId($post['idMapping']));
                $fields = array("fields");
                $mapping = self::getMappings($where, $fields);
                $arrayMapping = $mapping[$post['idMapping']]["fields"];
            }
            else
                $arrayMapping = array();

            if(!empty($post["path"])){
                $json = json_decode($json,true);
                $json = $json[$post["path"]];
                $json = json_encode($json);
            }

            if(substr($json, 0,1) == "{")
                $arbre = ArrayHelper::getAllPathJson($json); 
            else{
                $arbre = array();
                foreach (json_decode($json,true) as $key => $value) {
                    $arbre = ArrayHelper::getAllPathJson(json_encode($value), $arbre); 
                }
            }
            $attributesElt = ArrayHelper::getAllPathJson(file_get_contents("../../modules/communecter/data/import/".Element::getControlerByCollection($post["typeElement"]).".json", FILE_USE_INCLUDE_PATH));
            $params = array("result"=>true,
                        "attributesElt"=>$attributesElt,
                        "arrayMapping"=>$arrayMapping,
                        "arbre"=>$arbre,
                        "typeFile"=>$post['typeFile']);          
        }
        return $params ;
    }

    public static function getMappings($where=array(),$fields=null){
        $allMapping = PHDB::find(self::MAPPINGS, $where, $fields);
        return $allMapping;
    }

   

    public static  function previewData($post){
        $params = array("result"=>false);
        $elements = array();
        $nb = 0 ;
        $elementsWarnings = array();
        if(!empty($post['infoCreateData']) && !empty($post['file']) && !empty($post['nameFile']) && !empty($post['typeFile'])){
            $mapping = json_decode(file_get_contents("../../modules/communecter/data/import/".Element::getControlerByCollection($post["typeElement"]).".json", FILE_USE_INCLUDE_PATH), true);
            $attributesElt = ArrayHelper::getAllPathJson(file_get_contents("../../modules/communecter/data/import/".Element::getControlerByCollection($post["typeElement"]).".json", FILE_USE_INCLUDE_PATH));
            if($post['typeFile'] == "csv"){
                $file = $post['file'];
                $headFile = $file[0];
                unset($file[0]);
            }elseif ((!isset($post['pathObject'])) || ($post['pathObject'] == "")) {
                $file = json_decode($post['file'][0], true);
                // var_dump($file);
            }else {
                // $file = json_decode($post["file"][0][$post["pathObject"]][0], true);
                $file = json_decode($post['file'][0], true);
                // $file = json_encode($file);
                $file = $file[$post["pathObject"]];
                // $file = json_decode($file,true);
            }
            
            foreach ($file as $keyFile => $valueFile){
                $nb++;
                //if(!empty($valueFile)){
                    $element = array();
                    foreach ($post['infoCreateData'] as $key => $value) {
                        $valueData = null;

                        //var_dump($valueFile);
                        //var_dump($value);
                        if($post['typeFile'] == "csv" && in_array($value["idHeadCSV"], $headFile)){
                            $idValueFile = array_search($value["idHeadCSV"], $headFile);
                            $valFile =  (!empty($valueFile[$idValueFile])?$valueFile[$idValueFile]:null);
                        }else if ($post['typeFile'] == "json"){
                            $valFile =  ArrayHelper::getValueByDotPath($valueFile , $value["idHeadCSV"]);
                        }
                        else{
                            $valFile =  (!empty($valueFile[$value["idHeadCSV"]])?$valueFile[$value["idHeadCSV"]]:null);
                        }


                        //var_dump($valFile);
                        if(!empty($valFile)){
                            $valueData = ( is_string($valFile) ? trim($valFile) : $valFile );
                            if(!empty($valueData)){                               
                                $typeValue = ArrayHelper::getValueByDotPath($mapping , $value["valueAttributeElt"]);
                                $element = ArrayHelper::setValueByDotPath($element , $value["valueAttributeElt"], $valueData, $typeValue);
                                //var_dump($typeValue);
                                //var_dump($element);
                            }
                        }
                    }
                    $element['source']['insertOrign'] = "import";
                    if(!empty($post['key'])){
                        $element['source']['keys'][] = $post['key'];
                        $element['source']['key'] = $post['key'];
                    }

                    $element = self::checkElement($element, $post['typeElement']);

                    if($post['typeElement'] == Person::COLLECTION){
                        $element['msgInvite'] = $post['msgInvite'];
                        $element['nameInvitor'] = $post['nameInvitor'];
                    }

                    if(!empty($element['msgError']) || ($post['warnings'] == "false" && !empty($element['warnings'])))
                        $elementsWarnings[] = $element;
                    else
                        $elements[] = $element;
                //}
            }
           // var_dump($nb);
            $params = array("result"=>true,
                            "elements"=>json_encode(json_decode(json_encode($elements),true)),
                            "elementsWarnings"=>json_encode(json_decode(json_encode($elementsWarnings),true)),
                            "listEntite"=>self::createArrayList(array_merge($elements, $elementsWarnings)));
        }

        return $params;
    }  

    public static function createArrayList($list) {
        $head = array("name", "address", "warnings", "msgError") ;
        $tableau = array($head);
        foreach ($list as $keyList => $valueList){
            $ligne = array();
            $ligne[] = (empty($valueList["name"])? "" : $valueList["name"]);

            if(!empty($valueList["address"])){
                $str = (empty($valueList["address"]["streetAddress"]) ? "" : $valueList["address"]["streetAddress"].",");
                $str .= (empty($valueList["address"]["postalCode"]) ? "" : $valueList["address"]["postalCode"].", ");
                $str .= (empty($valueList["address"]["addressLocality"]) ? "" : $valueList["address"]["addressLocality"].", ");
                $str .= (empty($valueList["address"]["addressCountry"]) ? "" : $valueList["address"]["addressCountry"]);
                $ligne[] = $str;
            }

            $ligne[] = (empty($valueList["warnings"])? "" : self::getMessagesWarnings($valueList["warnings"]));
            $ligne[] = (empty($valueList["msgError"])? "" : $valueList["msgError"]);
            $tableau[] = $ligne ;
        }
        return $tableau ;
    }

    public static function getMessagesWarnings($warnings){
        $msg = "";
        foreach ($warnings as $key => $codeWarning) {
            if($msg != "")
                $msg .= "<br/>";
            $msg .= Yii::t("import",$codeWarning, null, Yii::app()->controller->module->id);
        }
        return $msg;
    }

    public static function checkElement($element, $typeElement){
        $result = array("result" => true);
        
        if($typeElement != Person::COLLECTION){
            $address = (empty($element['address']) ? null : $element['address']);
            $geo = (empty($element['geo']) ? null : $element['geo']);

            if(!empty($address) && !empty($address["addressCountry"])  && !empty($address["postalCode"]) && strtoupper($address["addressCountry"]) == "FR" && strlen($address["postalCode"]) == 4 )
                $address["postalCode"] = '0'.$address["postalCode"];

            $detailsLocality = self::getAndCheckAddressForEntity($address, $geo) ;
            if($detailsLocality["result"] == true){
               $element["address"] = $detailsLocality["address"] ;
               $element["geo"] = $detailsLocality["geo"] ;
               $element["geoPosition"] = $detailsLocality["geoPosition"] ; 
            }
        }

        if($typeElement == Event::COLLECTION || $typeElement == Project::COLLECTION){
            date_default_timezone_set('UTC');
            if(!empty($element['startDate']))
                $element['startDate'] = date('Y-m-d H:i:s', strtotime($element['startDate']));
            
            if(!empty($element['endDate']))
                $element['endDate'] = date('Y-m-d H:i:s', strtotime($element['endDate']));
        }
        if(!empty($element["tags"]))
            $element["tags"] = self::checkTag($element["tags"]);
        
		if($typeElement == Organization::COLLECTION && !empty($element["type"]))
        	$element["type"] = Organization::translateType($element["type"]);

        if(!empty($element["facebook"]))
            $element["socialNetwork"]["facebook"] = $element["facebook"];

        $element = self::getWarnings($element, $typeElement, true) ;
        $resDataValidator = DataValidator::validate(Element::getControlerByCollection($typeElement), $element, true);

        if($resDataValidator["result"] != true){
            //$element["msgError"] = ((empty($resDataValidator["msg"]->getMessage()))?$resDataValidator["msg"]:$resDataValidator["msg"]->getMessage());
            $element["msgError"] = $resDataValidator["msg"];
        }
        
        return $element;
    }

    public static function checkTag($tags){
        $newTags = array();
        foreach ($tags as $key => $tag) {
           $split = explode(",", $tag);
           foreach ($split as $keyS => $value) {
               $newTags[] = trim($value);
           }
        }
        return $newTags;
    }


    public static function getAndCheckAddressForEntity($address = null, $geo = null){
        
        $result = array("result" => false);
        $newAddress = array(    '@type' => 'PostalAddress',
                                 'streetAddress' =>  (empty($address["streetAddress"])?'':$address["streetAddress"]), 
                                 'postalCode' =>  (empty($address["postalCode"])?'':$address["postalCode"]),
                                 'addressLocality' =>  (empty($address["addressLocality"])?'':$address["addressLocality"]),
                                 'addressCountry' =>  (empty($address["addressCountry"])?'':$address["addressCountry"]),
                                 'codeInsee' =>  '');

        $newGeo["geo"] = array(  "@type"=>"GeoCoordinates",
                        "latitude" => (empty($geo["latitude"])?'':$address["latitude"]),
                        "longitude" => (empty($geo["longitude"])?'':$address["longitude"]));

        $street = (empty($address["streetAddress"])?null:$address["streetAddress"]);
        $cp = (empty($address["postalCode"])?null:$address["postalCode"]);
        $nameCity = (empty($address["addressLocality"])?null:$address["addressLocality"]);
        $country = (empty($address["addressCountry"])?null:$address["addressCountry"]);
        $lat = (empty($geo["latitude"])?null:$geo["latitude"]);
        $lon = (empty($geo["longitude"])?null:$geo["longitude"]);

        //Cas 1 On a que l'addresse
        if(!empty($address) && empty($geo)){

            $resCedex = City::getCityByCedex($cp);
            if(!empty($resCedex)){
                $newGeo["geo"] = $resCedex["geo"];
                $newAddress["codeInsee"] = $resCedex["insee"];
                $newAddress['addressCountry'] = $resCedex["country"];
                $newAddress['addressLocality'] = $resCedex["name"];
                $newAddress['postalCode'] = $resCedex["cp"];
                $newAddress['regionName'] = $resCedex["regionName"];
                $newAddress['depName'] = $resCedex["depName"];
                $cedex = true;

            }else{
                $resultDataGouv = ( ( !empty($address["addressCountry"]) && $address["addressCountry"] == "FR" ) ? ( empty($cp) ? null : json_decode(SIG::getGeoByAddressDataGouv($street, $cp, $nameCity), true) ) : null ) ;
                if(!empty($resultDataGouv["features"])){
                    $newGeo["geo"]["latitude"] = strval($resultDataGouv["features"][0]["geometry"]["coordinates"][1]);
                    $newGeo["geo"]["longitude"] = strval($resultDataGouv["features"][0]["geometry"]["coordinates"][0]);
                }else{
                    $resultNominatim = json_decode(SIG::getGeoByAddressNominatim($street, $cp, $nameCity, $country), true);
                    if(!empty($resultNominatim[0])){
                        $newGeo["geo"]["latitude"] = $resultNominatim[0]["lat"];
                        $newGeo["geo"]["longitude"] = $resultNominatim[0]["lon"];
                    }else{
                        $resultGoogle = json_decode(SIG::getGeoByAddressGoogleMap($street, $cp, $nameCity, $country), true);
                        $resG = false ;
                        if(!empty($resultGoogle["results"][0]["address_components"])){
                            foreach ($resultGoogle["results"][0]["address_components"] as $key => $value) {
                                if(in_array("locality", $value["types"]))
                                    $resG = true ;
                            }
                        }
                        
                        //var_dump($resG);
                        if(!empty($resultGoogle["results"]) && $resG == true){
                            $newGeo["geo"]["latitude"] = strval($resultGoogle["results"][0]["geometry"]["location"]["lat"]);
                            $newGeo["geo"]["longitude"] = strval($resultGoogle["results"][0]["geometry"]["location"]["lng"]);
                        }else{
                            $resultDataGouv = ( ( !empty($address["addressCountry"]) && $address["addressCountry"] == "FR" ) ? ( empty($cp)?null:json_decode(SIG::getGeoByAddressDataGouv(null, $cp, $nameCity), true) ) : null ) ;
                            if(!empty($resultDataGouv["features"])){
                                $newGeo["geo"]["latitude"] = strval($resultDataGouv["features"][0]["geometry"]["coordinates"][1]);
                                $newGeo["geo"]["longitude"] = strval($resultDataGouv["features"][0]["geometry"]["coordinates"][0]);
                            }else{
                                $resultNominatim = json_decode(SIG::getGeoByAddressNominatim(null, $cp, $nameCity, $country), true);
                                if(!empty($resultNominatim[0])){
                                    $newGeo["geo"]["latitude"] = $resultNominatim[0]["lat"];
                                    $newGeo["geo"]["longitude"] = $resultNominatim[0]["lon"];
                                }else{
                                    $resultGoogle = json_decode(SIG::getGeoByAddressGoogleMap(null,$cp, $nameCity, $country), true);
                                    $resG = false ;
                                    if(!empty($resultGoogle["results"][0]["address_components"])){
                                        foreach ($resultGoogle["results"][0]["address_components"] as $key => $value) {
                                            if(in_array("locality", $value["types"]))
                                                $resG = true ;
                                        }
                                    }

                                    if(!empty($resultGoogle["results"]) && $resG == true){
                                        $newGeo["geo"]["latitude"] = strval($resultGoogle["results"][0]["geometry"]["location"]["lat"]);
                                        $newGeo["geo"]["longitude"] = strval($resultGoogle["results"][0]["geometry"]["location"]["lng"]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } // Cas 2 il n'y a que la Géo 
        else if(empty($address) && !empty($geo)){
            if(!empty($geo["latitude"]) && !empty($geo["longitude"])){

                if ((is_numeric($geo["latitude"])) && (is_numeric($geo["longitude"]))) {
                    $newGeo["geo"]["latitude"] = strval($geo["latitude"]) ;
                    $newGeo["geo"]["longitude"] =  strval($geo["longitude"]);
                }
                else {
                    $newGeo["geo"]["latitude"] = $geo["latitude"] ;
                    $newGeo["geo"]["longitude"] = $geo["longitude"];
                    $resultNominatim = json_decode(SIG::getLocalityByLatLonNominatim($geo["latitude"], $geo["longitude"]), true);
                    if(!empty($resultNominatim["address"]["postcode"])){
                        $arrayCP = explode(";", $resultNominatim["address"]["postcode"]);
                        $cp = $arrayCP[0];
                        $newAddress['postalCode'] = $arrayCP[0];
                    }
                }
            }
        }

        if(!empty($newGeo["geo"]["latitude"]) && !empty($newGeo["geo"]["longitude"])){
            if(empty($cedex)){
                $city = SIG::getCityByLatLngGeoShape($newGeo["geo"]["latitude"], $newGeo["geo"]["longitude"],$cp);
                if(empty($city)){
                    $cities = SIG::getCityByLatLng($newGeo["geo"]["latitude"], $newGeo["geo"]["longitude"], $cp);
                    $city = (!empty($cities[0])?$cities[0]:null);
                }
                if(empty($city))
                    $city = SIG::getCityByLatLngGeoShape($newGeo["geo"]["latitude"], $newGeo["geo"]["longitude"],null);
                if(!empty($city)){
                    $newAddress["codeInsee"] = $city["insee"];
                    $newAddress['addressCountry'] = $city["country"];
                    $newAddress['regionName'] = (@$city["regionName"]?$city["regionName"]:"");
                    $newAddress['depName'] = (@$city["depName"]?$city["depName"]:"");
                    foreach ($city["postalCodes"] as $keyCp => $valueCp){
                        if(empty($cp)){
                            if($valueCp["name"] == $city["alternateName"]){
                                $newAddress['addressLocality'] = $valueCp["name"];
                                $newAddress['postalCode'] = $valueCp["postalCode"];
                            }
                        }
                        else if($valueCp["postalCode"] == $cp){
                            $newAddress['addressLocality'] = $valueCp["name"];
                        }
                    }
                }
            }
            
            $newGeo["geoPosition"] = array("type"=>"Point",
                                                "coordinates" =>
                                                    array(
                                                        floatval($newGeo["geo"]['longitude']),
                                                        floatval($newGeo["geo"]['latitude'])));
            $result["result"] = true;
            $result["geoPosition"] = $newGeo["geoPosition"];
        }
        $result["geo"] = $newGeo["geo"];
        $result["address"] = $newAddress;
        return $result;
    }

    public static function getWarnings($element, $typeElement, $import = null){
        $warnings = array();

        if(empty($element['name']))
            $warnings[] = "201" ;

        if(empty($element['email']) && $typeElement == Person::COLLECTION)
            $warnings[] = "203" ;

        if($typeElement != Person::COLLECTION){
            if(!empty($element['address'])){
                if(empty($element['address']['postalCode'])) $warnings[] = "101" ;
                if(empty($element['address']['codeInsee'])) $warnings[] = "102" ;     
                if(empty($element['address']['addressCountry']))$warnings[] = "104" ;
                if(empty($element['address']['addressLocality'])) $warnings[] = "105" ;
            }else{
                $warnings[] = (empty($import)?"100":"110");
            }

            if(!empty($element['geo'])){
                if(empty($element['geo']['latitude'])) $warnings[] = "151" ;
                if(empty($element['geo']['longitude'])) $warnings[] = "152" ;
            }else {
                $warnings[] = (empty($import)?"150":"154") ;
            }
        }
        

        if(!empty($warnings))
            $element["warnings"] = $warnings;

        return $element;
    }

    public static function initMappings(){
        $mappings = json_decode(file_get_contents("../../modules/communecter/data/import/mappings.json", FILE_USE_INCLUDE_PATH), true);
        foreach ($mappings as $key => $value) {
            PHDB::insert( Import::MAPPINGS, $value );
        }
    }

     public static function addDataInDb($post, $moduleId = null)
    {
        $jsonString = $post["file"];
        $typeElement = $post["typeElement"];
        /*$pathFolderImage = $post["pathFolderImage"];

        $sendMail = ($post["sendMail"] == "false"?null:true);
        $isKissKiss = ($post["isKissKiss"] == "false"?null:true);
        $invitorUrl = (trim($post["invitorUrl"]) == ""?null:$post["invitorUrl"]);*/
        
        if(substr($jsonString, 0,1) == "{")
            $jsonArray[] = json_decode($jsonString, true) ;
        else
            $jsonArray = json_decode($jsonString, true) ;

        if(isset($jsonArray)){
            $resData =  array();
            foreach ($jsonArray as $key => $value){
                try{
                    if(@$value["address"]){
                        $exist = Element::alreadyExists($value, $typeElement);
                        if(!$exist["result"]) {
                            if(!empty($post["isLink"]) && $post["isLink"] == "true"){
                                if($post["typeLink"] == Event::COLLECTION && $typeElement == Event::COLLECTION){
                                    $value["parentId"] = $post["idLink"];
                                    $value["parentType"] = $post["typeLink"];
                                }
                                else{
                                    $paramsLink["idLink"] = $post["idLink"];
                                    $paramsLink["typeLink"] = $post["typeLink"];
                                    $paramsLink["role"] = $post["roleLink"];
                                }
                                
                            }

                            if(!empty($value["urlImg"])){
                                $paramsImg["url"] =$value["urlImg"];
                                $paramsImg["module"] = $moduleId;
                                $split = explode("/", $value["urlImg"]);
                                $paramsImg["name"] =$split[count($split)-1];

                            }
                            if(!empty($value["startDate"])){
                                $startDate = DateTime::createFromFormat('Y-m-d H:i:s', $value["startDate"]);
                                $value["startDate"] = $startDate->format('d/m/Y H:i');
                            }
                            if(!empty($value["endDate"])){
                                $endDate = DateTime::createFromFormat('Y-m-d H:i:s', $value["endDate"]);
                                $value["endDate"] = $endDate->format('d/m/Y H:i');
                            }

                            if(!empty($value["geo"])){
                                if(gettype($value["geo"]["latitude"]) != "string" )
                                    $value["geo"]["latitude"] = strval($value["geo"]["latitude"]);
                                if(gettype($value["geo"]["longitude"]) != "string" )
                                    $value["geo"]["longitude"] = strval($value["geo"]["longitude"]);
                            }


                            $value["collection"] = $typeElement ;
                            $value["key"] = Element::getControlerByCollection($typeElement);
                            $value["paramsImport"] = array( "link" => (empty($paramsLink)?null:$paramsLink),
                                                            "img" => (empty($paramsImg)?null:$paramsImg ));
                            $value["preferences"] = array(  "isOpenData"=>true, 
                                                            "isOpenEdition"=>true);

                            if($typeElement == Organization::COLLECTION)
                                $value["role"] = "creator";
                            if($typeElement == Event::COLLECTION && empty($value["organizerType"]))
                                $value["organizerType"] = Event::NO_ORGANISER;
                            
                            if(!empty($value["organizerId"])){

                                $eltSimple = Element::getElementSimpleById($value["organizerId"], @$value["organizerType"]);
                                if(empty($eltSimple)){
                                    unset($value["organizerId"]);
                                    if(!empty($value["organizerType"])) 
                                        $value["organizerType"] = Event::NO_ORGANISER;
                                }

                            }

                            $element = array();
                            $res = Element::save($value);
                            $element["name"] =  $value["name"];
                            $element["info"] = $res["msg"];
                            $element["type"] = $typeElement;
                            if(!empty($res["id"])){
                                $element["url"] = "/#".Element::getControlerByCollection($typeElement).".detail.id.".$res["id"] ;
                                $element["id"] = $res["id"] ;
                            }
                            
                        }else{
                            $element["name"] = $exist["element"]["name"];
                            $element["info"] = "L'élément existes déjà";
                            $element["url"] = "/#".Element::getControlerByCollection($typeElement).".detail.id.".(String)$exist["element"]["_id"] ;
                            $element["type"] = $typeElement ;
                            $element["id"] = (String)$exist["element"]["_id"] ;
                        }
                        
                    }else{
                        $element["name"] = $exist["element"]["name"];
                        $element["info"] = "L'élément n'a pas d'adresse.";  
                    }
                }
                catch (CTKException $e){
                    $element["name"] =  $value["name"];
                    $element["info"] = $e->getMessage();
                }
                $resData[] = $element;     
            }
            $params = array("result" => true, 
                            "resData" => $resData);
        }
        else
        {
            $params = array("result" => false); 
        }
      
        return $params;
    }

    

    public static  function setCedex($post){        
        if($post['typeFile'] == "csv"){
            $file = $post['file'];
            //$headFile = $file[0];
            unset($file[0]);
        }else{
            $file = json_decode($post['file'][0], true);
        }
        $bon = "";
        $erreur = "";
        $nb = 0;
        foreach ($file as $keyFile => $valueFile){

            if( !empty($valueFile) && !empty($valueFile[1]) && strlen(trim($valueFile[1])) > 5 && isset($valueFile[9]) && isset($valueFile[10]) ){
                $newCP = array();
                $cp = substr(trim($valueFile[1]), 0,5);
                $cedex = substr(trim($valueFile[1]), 5);
                $lat = $valueFile[9];
                $lon = $valueFile[10];

                $where = array("postalCodes.postalCode" => $cp);
                $existes = PHDB::find(City::COLLECTION, $where);

                if(empty($existes)){
                    $city = SIG::getCityByLatLngGeoShape($lat, $lon,null);

                    if(!empty($city)){
                        $newCP["postalCode"] = $cp;
                        $newCP["complement"] = trim($cedex);
                        $newCP["name"] = mb_strtoupper(trim($valueFile[2])).$cedex;
                        $newCP["geo"] = array(   "@type"=>"GeoCoordinates", 
                                        "latitude" => $lat, 
                                        "longitude" => $lon);
                        $newCP["geoPosition"] = array(   "type"=>"Point", 
                                                "coordinates" => array( floatval($lon), 
                                                                        floatval($lat)));
                        //var_dump($newCP);
                        $city["postalCodes"][] = $newCP;
                        
                        $res = PHDB::update( City::COLLECTION, 
                                array("_id"=>new MongoId((String)$city["_id"])),
                                array('$set' => array("postalCodes" => $city["postalCodes"])));
                        $nb++;
                        $bon .=  "<br> 'cp' : '".$cp."' , 'complement' : '".trim($cedex)."' , 'name' : '".trim($valueFile[2])."' ";
                    }else{
                        $erreur .=  "<br> 'error' : 'city not found' , cp' : '".$cp."' , 'complement' : '".trim($cedex)."' , 'name' : '".trim($valueFile[2])."' ";
                    }
                }else{
                    $erreur .=  "<br> 'error' : 'cp exist déjà' , cp' : '".$cp."' , 'complement' : '".trim($cedex)."' , 'name' : '".trim($valueFile[2])."' ";
                } 
            }
        }

        echo "Il y a ".$nb."update" ;
        echo "<br><br>---------------------<br><br>";
        echo $erreur ;
        echo "<br><br>---------------------<br><br>";
        echo $bon ;
    } 




    public static  function setWikiDataID($post){        
        if($post['typeFile'] == "csv"){
            $file = $post['file'];
            //$headFile = $file[0];
            unset($file[0]);
        }

        $bon = "";
        $erreur = "";
        $nb = 0;
        $elements = array();
        $elementsWarnings = array();
        foreach ($file as $keyFile => $valueFile){

            if( !empty($valueFile) && !empty($valueFile[0]) && isset($valueFile[2]) ){
                
                $insee = $valueFile[2];
                $split = explode("http://www.wikidata.org/entity/", $valueFile[0]) ;
                $wikidataID = $split[count($split)-1];
                

                $where = array("insee" => $insee);
                $city = PHDB::find(City::COLLECTION, $where);
                if(!empty($city)){
                    foreach ($city as $key => $value) {
                        $value["modifiedByBatch"][] = array("setWikiDataID" => new MongoDate(time()));

                        $res = PHDB::update( City::COLLECTION, 
                                    array("_id"=>new MongoId($key)),
                                    array('$set' => array(  "wikidataID" => $wikidataID,
                                                            "modifiedByBatch" => $value["modifiedByBatch"])));
                        $good = array();
                        $good["insee"] = $insee ;
                        $good["wikidataID"] = $wikidataID ;
                        $elements[] =  $good;
                    }
                    
                }else{
                    $error = array();
                    $error["insee"] = $insee ;
                    $error["wikidataID"] = $wikidataID ;
                    $elementsWarnings[] =  $error;
                } 
            }
        }
       // var_dump($elements);
        $params = array("result"=>true,
                            "elements"=>json_encode($elements),
                            "elementsWarnings"=>json_encode($elementsWarnings),
                            "listEntite"=>array());
        return $params ;
    }


    public static function isUncomplete($idEntity, $typeEntity){
        $res = false ;
        $entity = PHDB::findOne($typeEntity,array("_id"=>new MongoId($idEntity)));
        
        if(!empty($entity["warnings"]) || (!empty($entity["state"]) && $entity["state"] == "uncomplete"))
            $res = true;
        return $res ;
    }

    public static function checkWarning($idEntity, $typeEntity ,$userId){
        $entity = PHDB::findOne($typeEntity,array("_id"=>new MongoId($idEntity)));
        unset($entity["warnings"]);

        if($typeEntity == Project::COLLECTION){
            $newEntity = Project::getAndCheckProjectFromImportData($entity, $userId, null, true, true);
            if(!empty($newEntity["warnings"]))
                Project::updateProjectField($idEntity, "warnings", $newEntity["warnings"], $userId );
            else
                Project::updateProjectField($idEntity, "state", true, $userId ); 
        }

        if($typeEntity == Organization::COLLECTION){
            $newEntity = Organization::getAndCheckOrganizationFromImportData($entity, null, true, true);
            if(!empty($newEntity["warnings"])){
                Organization::updateOrganizationField($idEntity, "warnings", $newEntity["warnings"], $userId );
            }
            else{
                Organization::updateOrganizationField($idEntity, "state", true, $userId );
                Organization::updateOrganizationField($idEntity, "warnings", array(), $userId ); 
            }
                
        }

        if($typeEntity == Event::COLLECTION){
            $newEntity = Event::getAndCheckOrganizationFromImportData($entity, $userId, null, true, true);
            if(!empty($newEntity["warnings"]))
                Event::updateOrganizationField($idEntity, "warnings", $newEntity["warnings"], $userId );
            else
                Event::updateOrganizationField($idEntity, "state", true, $userId ); 
        }
    }

    public static function getParams($file, $type, $url) {

        $param = array();

        if ($type == Organization::COLLECTION) {
            $map = TranslateGeoJsonToPh::$mapping_organisation;
        } elseif ($type == Person::COLLECTION) {
            $map = TranslateGeoJsonToPh::$mapping_person;
        } elseif ($type == Event::COLLECTION) {
            $map = TranslateGeoJsonToPh::$mapping_event;
        } elseif ($type == Project::COLLECTION) {
            $map = TranslateGeoJsonToPh::$mapping_project;
        } 
        // else {
        //     return "Type d'élement non reconnu";
        // }

        $param['typeElement'] = $map["type_elt"];

        foreach ($map as $key => $value) {

            $param['infoCreateData'][$key]["valueAttributeElt"] = $value;
            $param['infoCreateData'][$key]["idHeadCSV"] = $key;

        }

        $param['typeFile'] = 'json';
        $param['pathObject'] = 'features';
        $param['nameFile'] = 'geojson';
        $param['key'] = 'geojson';
        $param['warnings'] = false;
        $param['nbTest'] = "5";

        if (( (isset($file)) || (isset($url)) ) && 
            (substr($url, 0, 35) !== "http://umap.openstreetmap.fr/en/map")) {

            $param['file'][0] = (isset($file)) ? $file : file_get_contents($url);
           
            $result = Import::previewData($param);
            $res = json_decode($result['elements']);

        } elseif ((isset($url)) && (substr($url, 0, 35) == "http://umap.openstreetmap.fr/en/map")) {
            $umap_data = file_get_contents($url);
            $list_url_data = Import::getDatalayersUmap($url);
            $param['nameFile'] = $url;
            $res = array();

            foreach ($list_url_data as $keyDatalayer => $valueDatalayer) {  

                $datalayers_data = file_get_contents($valueDatalayer);
                $param['file'][0] = $datalayers_data;

                $result = Import::previewData($param);

                $result = $result['elements'];

                if (!empty($result)) {
                    array_push($res, json_decode($result));
                }
            }

        } 

        if (isset($res)) {
            return $res;
        } 
        //else {
        //     return "Paramètre(s) manquant(s) ou erroné(s)";
        // }


    }

    public static function getDatalayersUmap($url){

        $url_map = $url;

        $umap_data = file_get_contents($url_map);
        $umap_data = json_decode($umap_data, true);

        $list_id = array();
        $list_url_datalayers = array();

        foreach ($umap_data["properties"]["datalayers"] as $key => $value) {

            $url_datalayers = 'http://umap.openstreetmap.fr/en/datalayer/'.$value['id'];
            array_push($list_url_datalayers, $url_datalayers);

        }

        $datalayers_data = file_get_contents($url_datalayers);

        return $list_url_datalayers;

    }

}

