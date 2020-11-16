<?php
spl_autoload_register(function ($class) {
    include 'classes/' . $class . '.class.php';
});

class PFunc {
    protected static $systemDict = array();
    protected static $userDict = array();
    protected static $dict =array(); 
    
    public function __construct(){
        self::$systemDict['LISTVOID']=self::isListVoid();
        self::$systemDict['[]']=self::isListVoid();

    }
    public function __call($name, $arguments) {       
        if(array_key_exists($name,self::$dict)=== FALSE) return;       
        return self::solve($name,...$arguments);       
    }
    public static function __callStatic($name, $arguments) {
        if(array_key_exists($name,self::$dict)=== FALSE) return;        
        return self::solve($name,...$arguments);
    }
    public function __get($name) {        
        if(array_key_exists($name,self::$dict)=== FALSE) return "unkwown";
        
        return self::solve($name);
    }
    public function __set($name,$value) {
        if(array_key_exists($name,self::$dict)=== TRUE) {
            unset(self::$dict[$name]);
        }
        $v = $value;
        if (!is_callable($value)){
            $v=function () use($value){return $value;};
        } 
        self::def($name,$v,true);
        
    }
    public function __isset($name) {
        if(array_key_exists($name,self::$dict)=== FALSE) return;
        return TRUE;
    }
    public function __unset($name) {
        if(array_key_exists($name,self::$dict)=== FALSE) return;
        unset(self::$dict[$name]); 
    }
    
    public static function dict(){
        return self::$dict;
    }

    public static function make_with_($name,$value){
        $v = $value;
        if (!is_callable($value)){
            $v=function () use($value){return $value;};
        } 
        self::def($name,$v,true);
    }
    // predicates
    public static function isListVoid(){
        return function($v) {return [is_array($v) && empty($v),[]]; };
    } 
    public static function isHeadTail(){
        return function($v) {return [(!empty($v)) && is_array($v),[array_shift($v),$v]]; };
    }

    public static function are_all_of_type(callable $is_function){
        return self::areAllOfType($is_function);
    }
    public static function areAllOfType_(callable $is_function){
        return function(...$args) use ($is_function){
            if(count($args)==0) return false;
            if(count($args)==1) return $is_function($args[0]);
            $cs=[];
            foreach($args as $arg){
                $cs[]=$is_function($arg);
            }
            $c=array_shift($cs);
            $ok =array_reduce($cs,fn($carry,$item)=> $carry &= $item,$c);
            return [ $ok, $args];
        };
    }
    public static function listVoid() {
        return [];
    }

    public static function headTail(){
        return function(array $v){
            if (!empty($v)) {
                $h = array_shift($v);
                $t = $v;
                return [$h,$t];
            }
        };
    }
    
    public static function head(array $xs){
        return array_shift($xs);
    }
    
    public static function tail(array $xs){
        array_shift($xs);
        return $xs;
    }



/*
    string->callable->callable->void
    name : def
    @param string $name, callable $fn, mixed $cond
    @return void
*/
public static function def($name,$value,$cond=true){
    $fn=$value;
    $c=self::predicatefn_($cond);
    if(!is_callable($value)){
        $fn = fn(...$args)=>$value;
    }        
    self::$dict[$name][]=['predicate'=>$c,'fn'=>$fn,'cond'=>$cond,'value'=>$value];
}

private static function condToFn74_($cond){
    
    if(is_callable($cond)) return $cond;
    if($cond ===TRUE) return fn(...$args)=>[true,$args];
    if($cond === NULL) return fn()=>[true,[]];
    if (!is_scalar($cond)) return fn(...$xs) => [$xs===$cond,$xs];
    if (is_bool($cond)) return fn(...$args) => [$cond,$args];
    return fn(...$args)=>[$args[0]===$cond,$args];
}
private static function condToFn73_($cond){
    if(is_callable($cond)) return $cond;
    if($cond ===TRUE) return function(){ return true; } ;
    $pat =$cond;
    if (is_scalar($cond)) {
        if(is_bool($cond)) { $pat = function(...$args) use ($cond) {return [$cond,$args];}; }
        else { $pat= function($x) use ($cond) { return [$x===$cond,[$x]];}; }
    } else {
        if(!is_callable($cond)) {
            $pat  = function(...$xs) {return [$xs===$cond,$xs];};
        }
    }
    return $pat;

}
public static function condfn_($cond){
    if (is_array($cond) && count($cond)==0) return self::isListVoid();
    if(is_callable($cond)) return $cond;
    if($cond ===TRUE) return fn(...$args)=>true;
    
    if (!is_scalar($cond)) return fn(...$xs) => $xs===$cond;
    if (is_bool($cond)) return fn(...$args) => $cond;
    return fn(...$args)=>$args[0]===$cond;

}

public static function predicatefn_($cond){
    if (is_array($cond) && count($cond)==0) return self::isListVoid();
    if(is_callable($cond)) return $cond;
    if($cond ===TRUE) return function(...$args){ return [true,$args]; } ;
    if(is_scalar($cond)) return self::condToFn74_($cond);
// [$x1,...$xn]
    if(is_iterable($cond)){
        $xs= [];
        foreach($cond as $k=>$v){
            $xs[$k]=self::condfn_($v);
        }
        return function(...$args) use ($xs){
            if (count($args)!=count($xs)) return FALSE;
            $cs=[];
            $ys=$args;
            $y=array_shift($ys);
            foreach($xs as $k=>$x){
                    $ws= $x($y);
                    $cs[]=$ws;


                    $y=array_shift($ys);
            }
            $c=array_shift($cs);
            $ar = (bool) array_reduce($cs,fn($carry,$item)=>$carry &= $item , $c);
            return  [$ar,$args];
        };
    }

}

    public static function solve($name, ...$args) {
        if(array_key_exists($name,self::$dict)===FALSE) return "unknown";
        foreach(self::$dict[$name] as $k=>$v){
            $fcond=$v['predicate'];
            $fn = $v['fn'];
            
            [$cond,$params] = $fcond(...$args);

            if($cond) return $fn(...$params);
        }

    }   

    public static function isEqualToZero(){
    //self::make_with_('isEqualTo0',fn($x)=>[($x==0),[$x]]);
    return  fn($x)=>[($x==0),[$x]];
    }

public static function isPositive(){
    return fn($x)=>[($x>0),[$x]];
}
public static function isNegative(){
    return fn($x)=>[($x<0),[$x]];
}
} // phs           

$app = new Pfunc;

$app->render = function(){};

$app->abc = 'coucou';
$app->abc ="hihihi";
$app->abc ="hohoho";
var_dump($app->abc);
$app->def('addx_y_',fn($x,$y)=>$x+$y,$app::areAllOfType_('is_int'));
$z=$app->addx_y_(2,3);
var_dump($z);
$app->hello = function (){return '<div>hello</div>' ;};
$app->home = fn()=>'<hello />';

$app->def('sign', fn($x) => 0, $app::isEqualToZero());

$app->def('sign', fn($x) => 1, $app::isPositive());
$app->def('sign', fn($x) => -1, $app::isNegative()); 
$z = $app->sign(12);
var_dump($z);
$z = $app->sign(-12);
var_dump($z);
$z = $app->sign(0);
var_dump($z);
// guards
$app->isEqualToZero=fn()=>$app::isEqualToZero();

$z = $app->isEqualToZero;
var_dump($z(12));


PFunc::def('mul', fn()=>1,[]);
PFunc::def('mul',fn($x,$xs)=>$x*PFunc::mul($xs),PFunc::isHeadTail());
echo PFunc::mul([1,2,3]),"\n";
exit;

