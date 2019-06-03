<?php

class R3Geom2d {

    // Gauss-Boaga to Cassini Soldner
    public $gb2cs = array('x' => -1640492.580,
        'y' => -5043415.06,
        'phi' => -0.01245868,
        's' => 1.00016858);
    public $gb2cs_c = array('n0' => -5043415.06,
        'e0' => -1640492.580,
        'a' => 1.00016858,
        'b' => -0.01245868);

    /**
     * Take the 'compiled' version of a scaled roto-translation
     * and apply it to point $x
     *
     * @param $Tc compiled parameters
     * @param $x  coordinates
     */
    function rot_trans_scale_c($Tc, $x) {

        $xr = array();
        $xr[0] = $Tc['e0'] + $Tc['a'] * $x[0] - $Tc['b'] * $x[1];
        $xr[1] = $Tc['n0'] + $Tc['a'] * $x[1] + $Tc['b'] * $x[0];
        return $xr;
    }

    /**
     * Take the 'compiled' version of a scaled roto-translation
     * and apply it to point $x, inverse version
     *
     * @param $Tc compiled parameters
     * @param $x  coordinates
     */
    function inv_rot_trans_scale_c($Tc, $x) {

        $xr = array();
        $hyp = pow($Tc['a'], 2.0) + pow($Tc['b'], 2.0);
        $xr[0] = (($Tc['b'] * ($x[1] - $Tc['n0']) +
                $Tc['a'] * ($x[0] - $Tc['e0']))) / $hyp;

        $xr[1] = -(($Tc['a'] * ($Tc['n0'] - $x[1]) +
                $Tc['b'] * ($x[0] - $Tc['e0']))) / $hyp;

//  $xr[1] = ($Tc['e0']-$x[0])/($Tc['a'] * $gamma) +
//  ($x[1] - $Tc['n0'])/($Tc['b'] * $gamma);
        return $xr;
    }

    function inv_rot_trans_scale($T) {
        $Ti = array();

        $Ti['x'] = -($T['x'] * cos(-$T['phi']) - $T['y'] * sin(-$T['phi'])) / $T['s'];
        $Ti['y'] = -($T['x'] * sin(-$T['phi']) + $T['y'] * cos(-$T['phi'])) / $T['s'];
        $Ti['s'] = 1.0 / $T['s'];
        $Ti['phi'] = -$T['phi'];

        return $Ti;
    }

    function rot_trans_scale($T, $x) {
        $xn = array();
        $xn[0] = $T['s'] * $T['x'] + $T['s'] * ($x[0] * cos($T['phi']) - $x[1] * sin($T['phi']));
        $xn[1] = $T['s'] * $T['y'] + $T['s'] * ($x[0] * sin($T['phi']) + $x[1] * cos($T['phi']));
        return $xn;
    }

    /**
     * Calculate the signed area of a simple polygon. There is no validation that
     * the argument is indeed a simple polygon, but the result may be shaky, if
     * it is not.
     *
     * Algorithm from http://www.exaflop.org/docs/cgafaq
     *
     * @param $polygon  array with the coordinates with the following
     *                  structure: array(array(x_0, y_0), ...)
     * @param $start    optional: first vertex of polygon
     * @param $end      optional: last vertex of polygon
     *
     * @returns the area of the polygon, + if anti-clockwise, - if clockwise
     */
    function polygon_signed_area($polygon, $start = 0, $end = null) {

        if ($end === null)
            $end = count($polygon) - 1;

        if ($start < $end)
            $inc = 1;
        else if ($start > $end)
            $inc = -1;
        else
            return 0.0;

        $v_old = null;
        $area = 0.0;
        $is_closed = false;
        for ($i = $start; $i != $end; $i += $inc) {
            if ($i == $start) {
                $v_old = $polygon[$i];
                continue;
            }
// cross product
            $area += $v_old[0] * $polygon[$i][1] - $v_old[1] * $polygon[$i][0];
            $v_old = $polygon[$i];
            if ($polygon[$i] == $polygon[$start]) {
                if ($i != $end)
                    echo "WARNING: polygon not simple, end found at vertex $i [end=$end]";

                $is_closed = true;
                break;
            }
        }
        return 0.5 * $area;
    }

    /**
     * Sum 2 2d-vectors
     * The the first argument is replaced with the result (to avoid memory
     * fragmentation)
     *
     * @param &$a  first term
     * @param $b   second term
     */
    function sum(&$a, $b) {
        $a[0] += $b[0];
        $a[1] += $b[1];
    }

    /**
     * Difference between 2 2d-vectors
     *
     * @param $a   first term
     * @param $b   second term
     *
     * @return difference
     */
    function diff($a, $b) {
        $d = array(0.0, 0.0);
        $d[0] = $a[0] - $b[0];
        $d[1] = $a[1] - $b[1];
        return $d;
    }

    /**
     * Norm
     *
     * @param $x   array
     *
     * @return difference
     */
    function norm1($x) {
        return abs($x[0]) + abs($x[1]);
    }

    /**
     * Norm
     *
     * @param $x   array
     *
     * @return difference
     */
    static function norm2($x) {
        return sqrt($x[0] * $x[0] + $x[1] * $x[1]);
    }

    static function innerProd(array $x1, array $x2) {
        return $x1[0] * $x2[0] + $x1[1] * $x2[1]; // inner product
    }

    static function crossProd(array $x1, array $x2) {
        return $x1[0] * $x2[1] - $x1[1] * $x2[0];
    }
    
    static function distance($x1, $x2) {
        $d = self::diff($x1, $x2);
        return self::norm2($d);
    }

    /**
     * Matrix vector product
     *
     * @param $m   matrix (2x2)
     * @param $x   vector
     *
     * @returns    $m * $x
     *
     * @author Peter Hopfgartner
     */
    function matvec($m, $x) {
        $rx = array(0.0, 0.0);
        $rx[0] = $m[0] * $x[0] + $m[1] * $x[1];
        $rx[1] = $m[2] * $x[0] + $m[3] * $x[1];
        return $rx;
    }

    /**
     * Rotate the vector $x by $phi radiants
     *
     * @param $phi rotation angle (in radiants)
     * @param $x   2d-vector, as array
     *
     * @returns    rotated vector, as array
     *
     * @author Peter Hopfgartner
     */
    function rot($x, $phi) {
        $rot2d = array(cos($phi), -sin($phi),
            sin($phi), cos($phi));
        $rx = self::matvec($rot2d, $x);
        return $rx;
    }

    /**
     * Transform polar coordinates in orthogonal coordinates
     *
     * @param $x   point in polar coordinates (r, phi)
     *
     * @returns array with x and y values
     */
    function polar2ortho($x) {
        $res = array(0.0, 0.0);
        $res[1] = $x[0] * sin($x[1]);
        $res[0] = $x[0] * cos($x[1]);
        return $res;
    }

    /**
     * Transform orthogonal coordinates in polar coordinates
     *
     * @param $x   point in orthogonal coordinates
     *
     * @returns array with r and phi values, angles are expressed in radiants
     */
    function ortho2polar($x) {

        $r = sqrt($x[0] * $x[0] + $x[1] * $x[1]);

        if ($x[0] == 0)
            if ($x[1] > 0)
                $phi = 0.5 * M_PI;
            else if ($x[1] < 0)
                $phi = -0.5 * M_PI;
            else
                $phi = NULL;
        else if ($x[0] > 0)
            $phi = atan($x[1] / $x[0]);
        else
            $phi = atan($x[1] / $x[0]) + M_PI;

        return array($r, $phi);
    }

    /**
     * Transform orthogonal coordinates in polar coordinates
     *  This returns always value 0 <= alpha <= 2 * M_PI
     *
     * @param $x   point in orthogonal coordinates
     *
     * @returns array with r and phi values, angles are expressed in radiants
     */
    function ortho2polar2($x) {

        $r = sqrt($x[0] * $x[0] + $x[1] * $x[1]);

        if ($x[0] == 0) {
            if ($x[1] > 0)
                $phi = 0.5 * M_PI;
            else if ($x[1] < 0)
                $phi = 1.5 * M_PI;
            else
                $phi = NULL;
        } else if ($x[0] > 0 && $x[1] > 0) {
            $phi = atan($x[1] / $x[0]);
        } else if ($x[0] < 0 && $x[1] > 0) {
            $phi = M_PI_2 - atan($x[1] / $x[0]);
        } else if ($x[0] < 0 && $x[1] < 0) {
            $phi = M_PI + atan($x[1] / $x[0]);
        } else {
            $phi = 3 * M_PI_2 - atan($x[1] / $x[0]);
        }
        return array($r, $phi);
    }

    /**
     * Return the view angle of a line segment. The value is positive, if in the
     * clockwise direction, the start node comes before the end node, negative else.
     *
     * @param array $x0 is the vector from the view point to the start node of the segment
     * @param array $x1 is the vector from the view point to the end node of the segment
     *
     * @return angle
     */
    function segmentAngle(array $x0, array $x1) {

        $l0 = self::norm2($x0);
        if ($l0 > 0) {
            $x0[0] /= $l0;
            $x0[1] /= $l0;
        }

        $l1 = self::norm2($x1);
        if ($l1 > 0) {
            $x1[0] /= $l1;
            $x1[1] /= $l1;
        }

        if ($l0 == 0.0 && $l1 == 0.0) {
            return NULL;
        }

        $crossProd = self::crossProd($x0, $x1);; // cross product is len(X0) * len(X1)*sin(alpha)
        $innerProd = self::innerProd($x0, $x1);

        $rv = NULL;
        if ($crossProd == 0.0 || $innerProd == 0.0) {
            // handle special cases
            if ($crossProd == 0.0) {
                if ($innerProd > 0) {
                    $rv = 0;
                } else {
                    $rv = M_PI;
                }
            } else {
                if ($crossProd > 0) {
                    $rv = M_PI_2;
                } else {
                    $rv = -M_PI_2;
                }
            }
            return $rv;
        }

        // TODO: assert $crossProd < 1
        if ($crossProd > 0) {
            if ($innerProd > 0) {
                $rv = asin($crossProd);
            } else {
                $rv = M_PI - asin($crossProd);
            }
        } else {
            if ($innerProd < 0) {
                $rv = -M_PI - asin($crossProd);
            } else {
                $rv = asin($crossProd);
            }
        }
        return $rv;
    }

    /**
     * Check if a point is in a polygon.
     *
     * @param array $polygon array with the polygon nodes, following the
     *                       common GIS convention, where the last node must
     *                       match the first node
     * @param array $p       point to be checked
     * 
     * @return boolean
     */
    static function point_in_polygon(array $polygon, array $p) {
        $windNumber = 0.0;
        $lastAngle = NULL;
        $nodes = count($polygon);
        if ($polygon[0][0] !== $polygon[$nodes - 1][0] || $polygon[0][1] !== $polygon[$nodes - 1][1]) {
            throw new Exception("last node must match first node");
        }

        for ($i = 1; $i < $nodes; $i++) {

            // check if point is on boundary
            $x0 = array($polygon[$i - 1][0] - $p[0], $polygon[$i - 1][1] - $p[1]); // vector point
            $x1 = array($polygon[$i][0] - $p[0], $polygon[$i][1] - $p[1]);

            $crossProd = $x0[0] * $x1[1] - $x0[1] * $x1[0];
            if ($crossProd == 0.0) {
                $innerProd = self::innerProd($x0, $x1);
                if ($innerProd <= 0) { // p is between
                    return true;
                }
            }

            $alpha = self::segmentAngle($x0, $x1);
            $windNumber += ( $alpha);
        }
        // TODO: assert that windnumer is always a multiple of 2*M_PI
        if (abs($windNumber) < 1e-3) {
            $rv = false;
        } else {
            $rv = true;
        }
        return $rv;
    }

    /**
     * Calculate Point of View: given 2 points with known absolute and local
     * position, the coordinates of the observer are returned.
     *
     * @param $local_pos     array with the 2 points in local coordinates
     * @param $abs_pos       array with the 2 points in absolute coordinates
     * @param $sloppy_checks set this to false, if the geometry should be checked
     *                       for geometrical accuracy
     */
    function get_pov($local_pos, $abs_pos, $sloppy_checks = false) {
        if (count($local_pos) != count($abs_pos))
            throw new FunctionArgumentsError('Arrays must have the same size');

        if (count($local_pos) < 2)
            throw new FunctionArgumentsError('At least 2 points must be given');

        $rtd = rot_trans_diff($local_pos, $abs_pos);
        $pov = diff2d($rtd['com_g'], $rtd['com_l']);

        return array($pov, $rtd['phi']);
    }

    /**
     * Return the modulus 2 M_PI of the argument
     * return argument modulus 2 M_PI
     * @param $phi
     *
     * @returns $phi mod 2 M_PI
     */
    function norm_angle($phi) {
        if ($phi < -M_PI) {
            $phi = $phi - 2 * M_PI * ceil($phi / (2 * M_PI));
        } else if ($phi > M_PI) {
            $phi = $phi - 2 * M_PI * floor($phi / (2 * M_PI));
        }
        return $phi;
    }

    /**
     * transform a point in UTM coordinates in a point in Gauss-Boaga coordinates
     * formulas are taken from:
     * Adozione del sistema Etrs89-Igm95, Geom. Antonino Di Girolamo,
     *   Regione Autonoma Trentino Alto Adige
     *
     * @param $p_utm  point in UTM coordinates (east, north)
     *
     * @returns point in Gauss-Boaga coordinates
     */
    function UTM2GB2d($p_utm) {
        static $rotscal = array(1.00001807028866, -8.0891327428788e-6,
    8.0891327428788e-6, 1.00001807028866);
        static $transl = array(1000058.1598305, -74.833410854763);

        $p_gb = matvec2d($rotscal, $p_utm);
        sum2d($p_gb, $transl);
        return $p_gb;
    }

    /**
     * transform a point in Gauss Boaga coordinates in a point in UTM coordinates
     * formulas are taken from:
     * Adozione del sistema Etrs89-Igm95, Geom. Antonino Di Girolamo,
     *   Regione Autonoma Trentino Alto Adige
     *
     * @param $p_utm  point in UTM coordinates (east, north)
     *
     * @returns point in Gauss-Boaga coordinates
     */
    function GB2UTM2d($p_gb) {
        static $rotscal = array(0.999981929972442, 8.08884040434622e-6,
    -8.08884040434622e-6, 0.999981929972442);
        static $transl = array(-1000040.08814668, 82.9213694651314);

        $p_utm = matvec2d($rotscal, $p_gb);
        sum2d($p_utm, $transl);
        return $p_utm;
    }

    /**
     * Calculate the center of mass
     * @param $points array with point coordinates array(array(X,Y), ...)
     *
     * @return array(X_CM, Y_CM)
     */
    function center_of_mass($points) {
        $X = 0.0;
        $Y = 0.0;
        $np = count($points);

        foreach ($points as $p) {
            $X += $p[0];
            $Y += $p[1];
        }
        return array($X / $np, $Y / $np);
    }

    /**
     * Calculate the translation, scale and rotation
     *
     * @param $p_l array with local coordinates, array(array(X_LOC, Y_LOC), ...)
     * @param $p_l array with global coordinates, array(array(X_LOC, Y_LOC), ...)
     *
     * @return array with the rotation, translation and scale parameters
     *   	         array('com_l' => array(X_CM_LOC, Y_CM_LOC),
     * 					   'com_g' => array(X_CM_GLOB, Y_CM_GLOB),
     *                     't'     => array(X_TRANS, Y_TRANS),
     * 	                   's'     => SCALE,
     *                     'phi'   => ROTATION_ANGLE)
     */
    static function getRotTransDiff($p_l, $p_g) {
        $np = count($p_l);

        if ($np != count($p_g))
            return null;

        $com_l = self::center_of_mass($p_l);
        $com_g = self::center_of_mass($p_g);

        $t = self::diff($com_g, $com_l);

// calculate rotation and scale
        $s = array();
        array_pad($s, $np, -1.0);
        $phi = array();
        array_pad($phi, $np, -10.0);

        $w = 0;
        for ($i = 0; $i < $np; $i++) {
            $dx_l = self::diff($p_l[$i], $com_l);
            $pp_l = self::ortho2polar($dx_l);

            $dx_g = self::diff($p_g[$i], $com_g);
            $pp_g = self::ortho2polar($dx_g);

            $s[$i] = $pp_g[0] / $pp_l[0];
            $phi[$i] = self::norm_angle($pp_g[1] - $pp_l[1]);
            $w += 0.5 * ($pp_g[0] + $pp_l[0]);
            $sumPhi = $phi[$i] * 0.5 * ($pp_g[0] + $pp_l[0]);
        }

        $s_m = array_sum($s) / $np;
        $phi_m = array_sum($phi) / $np;
        $phi_mp = $sumPhi / $w;
        return array('com_l' => $com_l,
            'com_g' => $com_g,
            't' => $t,
            's' => $s_m,
            'phi' => $phi_m);
    }

}


class R3GeomBox {
    
    static function center(array $box) {
        $center = array(
                0.5 * ($box[0] + $box[2]),
                0.5 * ($box[1] + $box[3]),
        );
        return $center;
    }
    
    /**
     * Calculate the square box, that contains this box
     * 
     * @param array $box
     * @return array 
     */
    static function exSquare(array $box) {
        $xEdgeLength = $box[2] - $box[0];
        $yEdgeLength = $box[3] - $box[1];
        if ($xEdgeLength == $yEdgeLength) {
            return $box;
        }
        $center = self::center($box);
        $longestLength = max($xEdgeLength, $yEdgeLength);
        $newBox = array(
            $center[0] - ($longestLength / 2),
            $center[1] - ($longestLength / 2),
            $center[0] + ($longestLength / 2),
            $center[1] + ($longestLength / 2)
        );
        return $newBox;
    }

    /**
     * Resize box so that a minimum size is guaranteed
     * 
     * @param array $box     Box
     * @param array $minSize Minimum sizes
     * @param type $orGreater Allow greater sizes, if box is greater
     * @return array
     */
    static function resize(array $box, array $minSize, $orGreater = false) {
        $center = self::center($box);
        if (count($box) != 4) {
            throw new Exception("box must have 4 elements");
        }
        if (count($minSize) != 2) {
            throw new Exception("minSize must have 2 elements");
        }
        
        $xsize = $minSize[0];
        if (($box[2] - $box[0]) > $minSize[0] && $orGreater) {
            // if X does not fit, increase Y too ...
            $minSize[1] *= ($box[2] - $box[0])/$xsize;
            $xsize = $box[2] - $box[0];
        }
        
        $ysize = $minSize[1];
        if (($box[3] - $box[1]) > $minSize[1] && $orGreater) {
            // if Y does not fit, increase X too ...
            $xsize *= ($box[3] - $box[1])/$xsize;
            $ysize = $box[3] - $box[1];
        }
        
        $retval = array(
            $center[0] - ($xsize / 2),
            $center[1] - ($ysize / 2),
            $center[0] + ($xsize / 2),
            $center[1] + ($ysize / 2)
        );
        return $retval;
    }
    
    /**
     * Calc the MBR box of a boxes collection
     * 
     * @param array $boxes
     * @return array 
     */
    static function aggregate(array $boxes) {
        $retval = array(NULL, NULL, NULL, NULL);
        foreach($boxes as $box) {
            if (is_null($retval[0]) || $box[0] < $retval[0]) {
                $retval[0] = $box[0];
            }
            if (is_null($retval[1]) || $box[1] < $retval[1]) {
                $retval[1] = $box[1];
            }
            if (is_null($retval[2]) || $box[2] > $retval[2]) {
                $retval[2] = $box[2];
            }
            if (is_null($retval[3]) || $box[3] > $retval[3]) {
                $retval[3] = $box[3];
            }
        }
        return $retval;
    }
    
}