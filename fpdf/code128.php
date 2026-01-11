<?php
/**
 * PDF_Code128 — Code 128 nativo para FPDF (sin imágenes PNG)
 * Autor original: Roland Gautier — adaptado para FPDF 1.8+
 * Archivo auto-contenible.
 */

if (!class_exists('FPDF')) {
    // Ajusta la ruta si tu fpdf.php está en otra carpeta
    require_once __DIR__ . '/fpdf/fpdf.php';
}

class PDF_Code128 extends FPDF
{
    protected $T128 = [];
    protected $ABCset = "";
    protected $Aset = "";
    protected $Bset = "";
    protected $Cset = "";
    protected $SetFrom = [];
    protected $SetTo   = [];
    protected $JStart  = ["A"=>103, "B"=>104, "C"=>105];
    protected $JSwap   = ["A"=>101, "B"=>100, "C"=> 99];

    public function __construct($orientation='P', $unit='mm', $size='A4')
    {
        parent::__construct($orientation, $unit, $size);

        // Tabla de módulos para cada símbolo
        $this->T128 = [
            [2,1,2,2,2,2],[2,2,2,1,2,2],[2,2,2,2,2,1],[1,2,1,2,2,3],[1,2,1,3,2,2],
            [1,3,1,2,2,2],[1,2,2,2,1,3],[1,2,2,3,1,2],[1,3,2,2,1,2],[2,2,1,2,1,3],
            [2,2,1,3,1,2],[2,3,1,2,1,2],[1,1,2,2,3,2],[1,2,2,1,3,2],[1,2,2,2,3,1],
            [1,1,3,2,2,2],[1,2,3,1,2,2],[1,2,3,2,2,1],[2,2,3,2,1,1],[2,2,1,1,3,2],
            [2,2,1,2,3,1],[2,1,3,2,1,2],[2,2,3,1,1,2],[3,1,2,1,3,1],[3,1,1,2,2,2],
            [3,2,1,1,2,2],[3,2,1,2,2,1],[3,1,2,2,1,2],[3,2,2,1,1,2],[3,2,2,2,1,1],
            [2,1,2,1,2,3],[2,1,2,3,2,1],[2,3,2,1,2,1],[1,1,1,3,2,3],[1,3,1,1,2,3],
            [1,3,1,3,2,1],[1,1,2,3,1,3],[1,3,2,1,1,3],[1,3,2,3,1,1],[2,1,1,3,1,3],
            [2,3,1,1,1,3],[2,3,1,3,1,1],[1,1,2,1,3,3],[1,1,2,3,3,1],[1,3,2,1,3,1],
            [1,1,3,1,2,3],[1,1,3,3,2,1],[1,3,3,1,2,1],[3,1,3,1,2,1],[2,1,1,3,3,1],
            [2,3,1,1,3,1],[2,1,3,1,1,3],[2,1,3,3,1,1],[2,1,3,1,3,1],[3,1,1,1,2,3],
            [3,1,1,3,2,1],[3,3,1,1,2,1],[3,1,2,1,1,3],[3,1,2,3,1,1],[3,3,2,1,1,1],
            [3,1,4,1,1,1],[2,2,1,4,1,1],[4,3,1,1,1,1],[1,1,1,2,2,4],[1,1,1,4,2,2],
            [1,2,1,1,2,4],[1,2,1,4,2,1],[1,4,1,1,2,2],[1,4,1,2,2,1],[1,1,2,2,1,4],
            [1,1,2,4,1,2],[1,2,2,1,1,4],[1,2,2,4,1,1],[1,4,2,1,1,2],[1,4,2,2,1,1],
            [2,4,1,2,1,1],[2,2,1,1,1,4],[4,1,3,1,1,1],[2,4,1,1,1,2],[1,3,4,1,1,1],
            [1,1,1,2,4,2],[1,2,1,1,4,2],[1,2,1,2,4,1],[1,1,4,2,1,2],[1,2,4,1,1,2],
            [1,2,4,2,1,1],[4,1,1,2,1,2],[4,2,1,1,1,2],[4,2,1,2,1,1],[2,1,2,1,4,1],
            [2,1,4,1,2,1],[4,1,2,1,2,1],[1,1,1,1,4,3],[1,1,1,3,4,1],[1,3,1,1,4,1],
            [1,1,4,1,1,3],[1,1,4,3,1,1],[4,1,1,1,1,3],[4,1,1,3,1,1],[1,1,3,1,4,1],
            [1,1,4,1,3,1],[3,1,1,1,4,1],[4,1,1,1,3,1],[2,1,1,4,1,2],[2,1,1,2,1,4],
            [2,1,1,2,3,2],[2,3,3,1,1,1],[2,1] // END
        ];

        // Sets elegibles
        for ($i=32; $i<=95; $i++) $this->ABCset .= chr($i);
        $this->Aset = $this->ABCset;
        $this->Bset = $this->ABCset;
        for ($i=0; $i<=31; $i++)   { $this->ABCset .= chr($i); $this->Aset .= chr($i); }
        for ($i=96; $i<=127; $i++) { $this->ABCset .= chr($i); $this->Bset .= chr($i); }
        for ($i=200; $i<=210; $i++) { $this->ABCset .= chr($i); $this->Aset .= chr($i); $this->Bset .= chr($i); }
        $this->Cset = "0123456789".chr(206);

        // Conversores A/B
        for ($i=0; $i<96; $i++) {
            @$this->SetFrom["A"] .= chr($i);
            @$this->SetFrom["B"] .= chr($i+32);
            @$this->SetTo["A"]   .= chr(($i<32)? $i+64 : $i-32);
            @$this->SetTo["B"]   .= chr($i);
        }
        for ($i=96; $i<107; $i++) {
            @$this->SetFrom["A"] .= chr($i+104);
            @$this->SetFrom["B"] .= chr($i+104);
            @$this->SetTo["A"]   .= chr($i);
            @$this->SetTo["B"]   .= chr($i);
        }
    }

    /**
     * Dibuja un Code128 en (x,y) con ancho total $w y alto $h (mm).
     */
    public function Code128($x, $y, $code, $w, $h)
    {
        // Guías de sets
        $Aguid = $Bguid = $Cguid = "";
        $len = strlen($code);
        for ($i=0; $i<$len; $i++) {
            $ch = $code[$i];
            $Aguid .= (strpos($this->Aset,$ch)===false)?'N':'O';
            $Bguid .= (strpos($this->Bset,$ch)===false)?'N':'O';
            $Cguid .= (strpos($this->Cset,$ch)===false)?'N':'O';
        }

        $crypt = "";
        $SminiC = "OOOO"; $IminiC = 4;

        while ($code !== "") {
            $i = strpos($Cguid, $SminiC);
            if ($i !== false) { $Aguid[$i]='N'; $Bguid[$i]='N'; }

            if (substr($Cguid,0,$IminiC) === $SminiC) {
                $crypt .= chr(($crypt!=="") ? $this->JSwap["C"] : $this->JStart["C"]);
                $made = strpos($Cguid,'N'); if ($made===false) $made = strlen($Cguid);
                if ($made % 2 === 1) $made--;
                for ($i=0; $i<$made; $i+=2) $crypt .= chr(intval(substr($code,$i,2)));
                $jeu = "C";
            } else {
                $madeA = strpos($Aguid,'N'); if ($madeA===false) $madeA = strlen($Aguid);
                $madeB = strpos($Bguid,'N'); if ($madeB===false) $madeB = strlen($Bguid);
                $made = ($madeA < $madeB) ? $madeB : $madeA;
                $jeu  = ($madeA < $madeB) ? "B" : "A";
                $crypt .= chr(($crypt!=="") ? $this->JSwap[$jeu] : $this->JStart[$jeu]);
                $crypt .= strtr(substr($code,0,$made), $this->SetFrom[$jeu], $this->SetTo[$jeu]);
            }

            $code  = substr($code,$made);
            $Aguid = substr($Aguid,$made);
            $Bguid = substr($Bguid,$made);
            $Cguid = substr($Cguid,$made);
        }

        // Suma de control
        $check = ord($crypt[0]);
        $len = strlen($crypt);
        for ($i=0; $i<$len; $i++) $check += (ord($crypt[$i]) * $i);
        $check %= 103;

        // Secuencia completa
        $crypt .= chr($check).chr(106).chr(107);

        // Módulo (ancho de “unidad”)
        $moduleCount = (strlen($crypt) * 11) - 8;
        $mod = $w / $moduleCount;

        // Dibujar
        for ($i=0; $i<strlen($crypt); $i++) {
            $c = $this->T128[ord($crypt[$i])];
            for ($j=0; $j<count($c); $j++) {
                $this->Rect($x, $y, $c[$j]*$mod, $h, 'F');
                $x += ($c[$j++]+$c[$j]) * $mod;
            }
        }
    }

    /**
     * Helper: centra el código en el ancho indicado y escribe el texto debajo.
     */
    public function Code128Centered($centerX, $y, $code, $totalWidth, $h, $fontSize=8)
    {
        $moduleCount = (strlen($this->encodeForWidth($code)) * 11) - 8;
        $mod = $totalWidth / $moduleCount;
        $x = $centerX - ($totalWidth/2);
        $this->Code128($x, $y, $code, $totalWidth, $h);
        $this->SetFont('Arial','', $fontSize);
        $this->SetXY($x, $y + $h + 2);
        $this->Cell($totalWidth, 4, $code, 0, 1, 'C');
    }

    // Para calcular ancho sin dibujar
    protected function encodeForWidth($code)
    {
        $Aguid = $Bguid = $Cguid = "";
        $len = strlen($code);
        for ($i=0; $i<$len; $i++) {
            $ch = $code[$i];
            $Aguid .= (strpos($this->Aset,$ch)===false)?'N':'O';
            $Bguid .= (strpos($this->Bset,$ch)===false)?'N':'O';
            $Cguid .= (strpos($this->Cset,$ch)===false)?'N':'O';
        }
        $crypt=""; $SminiC="OOOO"; $IminiC=4;
        while ($code!=="") {
            $i = strpos($Cguid,$SminiC); if ($i!==false) { $Aguid[$i]='N'; $Bguid[$i]='N'; }
            if (substr($Cguid,0,$IminiC)===$SminiC) {
                $crypt .= chr(($crypt!=="")?$this->JSwap["C"]:$this->JStart["C"]);
                $made = strpos($Cguid,'N'); if ($made===false) $made=strlen($Cguid);
                if ($made%2===1) $made--;
                for($i=0;$i<$made;$i+=2) $crypt .= chr(intval(substr($code,$i,2)));
            } else {
                $madeA = strpos($Aguid,'N'); if ($madeA===false) $madeA=strlen($Aguid);
                $madeB = strpos($Bguid,'N'); if ($madeB===false) $madeB=strlen($Bguid);
                $made = ($madeA < $madeB) ? $madeB : $madeA;
                $jeu  = ($madeA < $madeB) ? "B" : "A";
                $crypt .= chr(($crypt!=="")?$this->JSwap[$jeu]:$this->JStart[$jeu]);
                $crypt .= strtr(substr($code,0,$made), $this->SetFrom[$jeu], $this->SetTo[$jeu]);
            }
            $code  = substr($code,$made);
            $Aguid = substr($Aguid,$made);
            $Bguid = substr($Bguid,$made);
            $Cguid = substr($Cguid,$made);
        }
        $check = ord($crypt[0]);
        for ($i=0;$i<strlen($crypt);$i++) $check += (ord($crypt[$i]) * $i);
        $check %= 103;
        return $crypt.chr($check).chr(106).chr(107);
    }
}