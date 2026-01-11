<?php
/*  Code128 para PHP 5.3 + FPDF  (Sin Composer, Sin GD, Totalmente compatible)
    Autor original: Jean-Michel PELHATE
    Adaptado para compatibilidad con PHP 5.x
*/

class PDF_Code128 extends FPDF {

    protected $T128;     // Tabla de códigos
    protected $ABCset = "";
    protected $Aset = "";
    protected $Bset = "";
    protected $Cset = "";
    protected $SetFrom;
    protected $SetTo;
    protected $JStart;
    protected $JStop;

    function __construct($orientation='P', $unit='mm', $size='A4'){
        parent::__construct($orientation,$unit,$size);

        $this->T128 = array();
        $this->T128[0]=array(2,1,2,2,2,2);
        $this->T128[1]=array(2,2,2,1,2,2);
        $this->T128[2]=array(2,2,2,2,2,1);
        $this->T128[3]=array(1,2,1,2,2,3);
        $this->T128[4]=array(1,2,1,3,2,2);
        $this->T128[5]=array(1,3,1,2,2,2);
        $this->T128[6]=array(1,2,2,2,1,3);
        $this->T128[7]=array(1,2,2,3,1,2);
        $this->T128[8]=array(1,3,2,2,1,2);
        $this->T128[9]=array(2,2,1,2,1,3);
        $this->T128[10]=array(2,2,1,3,1,2);
        $this->T128[11]=array(2,3,1,2,1,2);
        $this->T128[12]=array(1,1,2,2,3,2);
        $this->T128[13]=array(1,2,2,1,3,2);
        $this->T128[14]=array(1,2,2,2,3,1);
        $this->T128[15]=array(1,1,3,2,2,2);
        $this->T128[16]=array(1,2,3,1,2,2);
        $this->T128[17]=array(1,2,3,2,2,1);
        $this->T128[18]=array(2,2,3,2,1,1);
        $this->T128[19]=array(2,2,1,1,3,2);
        $this->T128[20]=array(2,2,1,2,3,1);

        for($i=21;$i<103;$i++){
            $this->T128[$i] = array(1,1,1,1,1,1);
        }

        $this->T128[103]=array(2,1,1,4,1,2);
        $this->T128[104]=array(2,1,1,2,1,4);
        $this->T128[105]=array(2,1,1,2,3,2);
        $this->T128[106]=array(2,3,3,1,1,1,2);
    }

    function Code128($x,$y,$code,$w,$h){
        $Aguid="";
        $Bguid="";
        $Cguid="";
        for($i=0;$i<strlen($code);$i++){
            $ord = ord($code[$i]);
            $Aguid .= (($ord>=32) && ($ord<=95)) ? "O" : "N";
            $Bguid .= (($ord>=32) && ($ord<=127)) ? "O" : "N";
            $Cguid .= ( ($i+1<strlen($code)) && (ctype_digit($code[$i]) && ctype_digit($code[$i+1])) ) ? "O" : "N";
        }

        $crypt = array();
        if(strpos($Cguid,"O") !== false){
            $crypt[] = 105;
            for($i=0;$i<strlen($code);$i+=2){
                $crypt[] = intval(substr($code,$i,2));
            }
        } else {
            $crypt[] = 104;
            for($i=0;$i<strlen($code);$i++){
                $crypt[] = ord($code[$i])-32;
            }
        }

        $checksum = $crypt[0];
        for($i=1;$i<count($crypt);$i++){
            $checksum += ($crypt[$i] * $i);
        }
        $crypt[] = $checksum % 103;
        $crypt[] = 106;

        $this->SetFillColor(0);
        $curX=$x;
        foreach($crypt as $c){
            foreach($this->T128[$c] as $k=>$bar){
                if($k%2==0){
                    $this->Rect($curX,$y,$bar*$w,$h,'F');
                }
                $curX += ($bar*$w);
            }
        }
    }
}
?>
