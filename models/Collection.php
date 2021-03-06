<?php 
class Collection {

    public static function create($name)
    {
        
        $person = Person::getById( Yii::app()->session["userId"] );
        $action = '$set';        
        PHDB::update(Person::COLLECTION, 
                       array("_id" => new MongoId(Yii::app()->session["userId"]) ) , 
                       array($action => array("collections.".$name => new stdClass() )));

        return array("result"=>true, "msg"=>Yii::t("common","Collection {what} created with success",array("{what}"=>$name)));
    }

    public static function update($name,$newName,$del=false)
    {
        
        $person = Person::getById( Yii::app()->session["userId"] );     

        if( isset($person["collections"][$name]) )
        {
            $actions = array();
            $action = "deleted";
            if(!$del){
                $actions['$set'] = array("collections.".$newName => $person["collections"][$name]);
                $action = "updated";
            }

            $actions['$unset'] = array("collections.".$name => true);
            PHDB::update(Person::COLLECTION, 
                           array("_id" => new MongoId(Yii::app()->session["userId"]) ) , 
                           $actions
                           );
            return array("result"=>true, "msg"=>Yii::t("common","Collection {what} ".$action." with success",array("{what}"=>$name)));
        } else 
            return array("result"=>false, "collection"=>"collections.".$name, "msg"=>"Collection $name doesn't exist");
        
    }

    public static function add($targetId, $targetType,$collection="favorites")
    {
        
        $person = Person::getById( Yii::app()->session["userId"] );
        $target = Element::checkIdAndType( $targetId, $targetType );
        $collections=array("collections.".$collection.".".$targetType.".".$targetId => new MongoDate(time()),"updated"=>time());
        
        $action = '$set';
        $inc = 1;
        $verb = "added";
        $linkVerb=Yii::t("common","to")." ".$collection;
        if($collection=="favorites")
            $linkVerb=Yii::t("common","to favorites");
        
        if( @$person["collections"][$collection][$targetType][$targetId] )
        {
            $action =  '$unset';
            $inc = -1;
            $verb = "removed";
            $linkVerb=Yii::t("common","from")." ".$collection;
            if($collection=="favorites")
                $linkVerb=Yii::t("common","from favorites");
        
            $collections=array("collections.".$collection.".".$targetType.".".$targetId => 1);
        }  

        PHDB::update(Person::COLLECTION, 
                       array("_id" => new MongoId(Yii::app()->session["userId"]) ) , 
                       array($action => $collections));

        PHDB::update($targetType, 
                       array( "_id" => new MongoId($targetId) ) , 
                       array( '$inc' => array( "collectionCount" => $inc ) ) );
            
        return array("result"=>true,"list"=>$action, "msg"=>Yii::t("common", "{what} ".$verb." {where} with success",array("{what}"=>$target["name"],"{where}"=>$linkVerb)));
    }

    //$type is a filter of a type of favorite
    public static function get($userId=null, $type=null,$collection="favorites")
    {
        if(!$userId)
            $userId = Yii::app()->session["userId"];
        $person = Person::getById( $userId );
        $list = array();
        $count = 0;
        if(@$person["collections"][$collection]){
            foreach ( @$person["collections"][$collection] as $favtype => $value ) 
            {
                $ids = array();
                if(!$type || $type == $favtype )
                {
                    foreach ($value as $id => $date) 
                    {
                        array_push($ids, new MongoId($id) );
                    }
                    if( count($ids) > 0)
                    {
                        $count += count($ids);
                        $list[$favtype] = PHDB::find($favtype,array( "_id" => array( '$in'=>$ids ) ));//,array("name","tags","profilMediumImageUrl")
                    }
                }
            }
        }
            
        return array("result"=>true, "count"=>$count,"list"=>$list);
    }
}