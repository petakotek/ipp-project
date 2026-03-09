<?php

namespace IPP\Classes;

use DOMElement;
class ObjectEntity
{
    // Testuje shodu dvou objektů, tj. že se jedná o tentýž objekt
    public function identicalTo()
    {

    }
    /// Datově porovná objekt: pokud objekt nemá interní atributy, invokuje identicalTo:,
    /// jinak porovnává interní atributy (v potomcích je možné equalTo: redefinovat tak, aby vhodně porovnávala i instanční atributy)
    public function equalTo($object)
    {

    }
    /// Vrací řetězec '' (v potomcích redefinováno rozumnější implementací)
    public function asString($object) : string
    {
        return '';
    }
    /// Vrací false. V potomcích jsou refefinovány tak, že vrací true ,pokud je příjemce instance Integer / String / Block / Nil
    /// (nebo jejjich podtřídy)
    public function isNumber() : bool
    {
        return false;
    }
    /// Vrací false. V potomcích True a False je redefinována tak, že vrací true.
    public function isBoolean() : bool {
        return false;
    }
}