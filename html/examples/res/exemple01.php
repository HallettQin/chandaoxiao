<?php
//cgxlm
echo "<style type=\"text/css\">\n<!--\ntable.morpion\n{\n    border:        dashed 1px #444444;\n}\n\ntable.morpion td\n{\n    font-size:    15pt;\n    font-weight:  bold;\n    border:       solid 1px #000000;\n    padding:      1px;\n    text-align:   center;\n    width:        25px;\n}\n\ntable.morpion td.j1 { color: #0A0; }\ntable.morpion td.j2 { color: #A00; }\n\n-->\n</style>\n<page style=\"font-size: 10pt\">\n    <span style=\"font-weight: bold; font-size: 20pt; color: #F00\">Bonjour, voici quelques exemples</span><br>\n    Bonjour, ceci est un test <b>de gras</b>, <i>d'italic</i>, <b><i>et des 2 ensembles</i></b>.<br>\n    <br>\n    <span style=\"background: red; color: white;\">Ceci est un message important</span><br>\n    <br>\n    <small>Texte écrit avec small</small>, Texte écrit normalement, <big>Texte écrit avec big</big><br>\n    <span style=\"font-size: 20px\">A<sub>test d'<b>indice</b></sub> et N<sup>test d'<b>exposant</b></sup>,\n        test<sub>test<sub>test</sub></sub>,\n        test<sup>test<sup>test</sup></sup>,\n        test<sub>test<sup>test</sup></sub>.\n    </span><br>\n    <br>\n    <table align=\"center\" style=\"border-radius: 6mm; border-top: solid 3mm #000077; border-right: solid 2mm #007700; border-bottom: solid 1mm #770000;    border-left: solid 2mm #007777;    background: #DDDDAA;\" ><tr><td style=\"width: 100mm; height: 20mm; text-align: center; \">Coucou ! ceci est un border solid avec un radius !!! </td></tr></table>\n    <br>\n    <table align=\"center\" style=\"border-radius: 6mm; border: none; background: #DDDDAA;\" ><tr><td style=\"width: 100mm; height: 20mm; text-align: center; \">Coucou ! ceci est un background avec un radius !!! </td></tr></table>\n    <br>\n    <table align=\"center\" style=\"border: solid 1px #000000;\"><tr><td style=\"border-top: solid 4mm #000077;  border-right: solid 3mm #007700;  border-bottom: solid 2mm #770000;  border-left: solid  1mm #007777; padding: 2mm 4mm 6mm 8mm; width: 100mm; background: #FFDDDD\">Coucou ! ceci est un border solid</td></tr></table><br>\n    <table align=\"center\" style=\"border: solid 1px #000000;\"><tr><td style=\"border-top: dotted 4mm #000077; border-right: dotted 3mm #007700; border-bottom: dotted 2mm #770000; border-left: dotted 1mm #007777; padding: 2mm 4mm 6mm 8mm; width: 100mm; background: #FFDDDD\">Coucou ! ceci est un border dotted</td></tr></table><br>\n    <table align=\"center\" style=\"border: solid 1px #000000;\"><tr><td style=\"border-top: dashed 4mm #000077; border-right: dashed 3mm #007700; border-bottom: dashed 2mm #770000; border-left: dashed 1mm #007777; padding: 2mm 4mm 6mm 8mm; width: 100mm; background: #FFDDDD\">Coucou ! ceci est un border dashed</td></tr></table><br>\n    <table align=\"center\" style=\"border: solid 1px #000000;\"><tr><td style=\"border-top: double 4mm #000077; border-right: double 3mm #007700; border-bottom: double 2mm #770000; border-left: double 1mm #007777; padding: 2mm 4mm 6mm 8mm; width: 100mm; background: #FFDDDD\">Coucou ! ceci est un border double</td></tr></table><br>\n";
$back = 'background-image: url(./res/off.png); background-position: left top; background-repeat: repeat; ';
echo "    <table style=\"background: #FFAAAA; color: #000022; border: 3px solid #555555;\">\n        <tr>\n            <td style=\"width: 40mm; border: solid 1px #000000; ";
echo $back;
echo "color: #003300\">Case A1</td>\n            <td style=\"width: 50mm; border: solid 1px #000000; ";
echo $back;
echo "font-weight: bold;\">Case A2</td>\n            <td style=\"width: 60mm; border: solid 1px #000000; ";
echo $back;
echo "font-size: 20px;\">Case A3</td>\n        </tr>\n        <tr>\n            <td style=\"border: solid 1px #000000; text-align: left;   ";
echo $back;
echo "vertical-align: top; \">Case B1</td>\n            <td style=\"border: solid 1px #000000; text-align: center; ";
echo $back;
echo "vertical-align: middle; height: 20mm\">Case B2<hr style=\"color: #22AA22\">test de hr</td>\n            <td style=\"border: solid 1px #000000; text-align: right;  ";
echo $back;
echo "vertical-align: bottom; border-radius: 3mm; \">Case B3</td>\n        </tr>\n    </table>\n    <br>\n    <table style=\"border: solid 2px #550000; background: #000022 url(./res/logo.png) center center no-repeat; color: #FFFFFF;\">\n        <tr >\n            <td style=\"border: solid 1px #AAAAAA;\">Case A1<BR>avec tests diverses</td>\n            <td style=\"border: solid 1px #AAAAAA;\">Case A2</td>\n            <td style=\"border: solid 1px #AAAAAA;\">Case A3 classic</td>\n        </tr>\n        <tr>\n            <td style=\"border: solid 1px #AAAAAA;\">Case B1<br>toto</td>\n            <td style=\"border: solid 1px #AAAAAA;background: #FF0000\">Case B2</td>\n            <td style=\"border: solid 1px #AAAAAA;\">Case B3</td>\n        </tr>\n    </table>\n    <br>\n    <table class=\"morpion\" cellspacing=\"5px\">\n        <tr>\n            <td class=\"j1\">X</td>\n            <td class=\"j2\">O</td>\n            <td class=\"j1\">X</td>\n        </tr>\n        <tr>\n            <td class=\"j2\">O</td>\n            <td class=\"j1\">X</td>\n            <td ></td>\n        </tr>\n        <tr>\n            <td class=\"j2\">O</td>\n            <td></td>\n            <td class=\"j1\">X</td>\n        </tr>\n    </table>\n    <br>\n    <table style=\"border: dotted 1mm #FFFFFF; background: #AAAAFF\">\n        <tr>\n            <td style=\"width: 42mm; text-align: center;font-size: 5mm\">\n                Ceci est un test\n            </td>\n        </tr>\n    </table>\n</page>";

?>
