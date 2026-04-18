<?php

namespace App\Support\Faker\Provider\eu_ES;

use Faker\Provider\Person as BasePerson;

/**
 * Basque (Euskara) Person provider for Faker.
 *
 * Used for youth academy and replenishment name generation at Basque clubs
 * (Athletic Bilbao, Real Sociedad, Alavés, Osasuna, Eibar — see
 * {@see \App\Modules\Squad\Configs\TeamRegionalOrigins}). Faker ships no
 * native eu_ES locale, so this provider fills that gap. Nationality stays
 * "Spain" at the data layer; only the name source changes.
 *
 * First-name and surname pools are drawn from commonly attested Basque
 * anthroponymy: modern given names popular in the Basque Country (Iker,
 * Mikel, Unai…) and recognisable Basque surnames (Etxeberria, Agirre,
 * Zubizarreta…). The pool is large enough for the retry loop in
 * PlayerGeneratorService to find non-colliding names across many seasons.
 */
class Person extends BasePerson
{
    protected static $firstNameMale = [
        'Aimar', 'Aitor', 'Aitzol', 'Ander', 'Aratz', 'Aritz', 'Asier',
        'Beñat', 'Bixente', 'Eder', 'Ekaitz', 'Eneko', 'Erlantz', 'Gaizka',
        'Gari', 'Garikoitz', 'Gorka', 'Haritz', 'Hodei', 'Ibai', 'Iban',
        'Iker', 'Imanol', 'Iñaki', 'Iñigo', 'Ioritz', 'Jokin', 'Jon',
        'Joseba', 'Josu', 'Julen', 'Kepa', 'Kerman', 'Koldo', 'Markel',
        'Mikel', 'Oier', 'Oihan', 'Oinatz', 'Oskar', 'Patxi', 'Pello',
        'Peru', 'Sendoa', 'Telmo', 'Txomin', 'Unai', 'Unax', 'Urko',
        'Urtzi', 'Xabi', 'Xabier', 'Xuban', 'Zuhaitz',
    ];

    protected static $firstNameFemale = [
        'Ainara', 'Ainhoa', 'Ainhize', 'Alazne', 'Amaia', 'Ane', 'Ariane',
        'Edurne', 'Eider', 'Elene', 'Eneritz', 'Garazi', 'Haizea', 'Idoia',
        'Irati', 'Iratxe', 'Itziar', 'Izaro', 'Izaskun', 'Leire', 'Maialen',
        'Maitane', 'Maite', 'Miren', 'Nahia', 'Naroa', 'Nerea', 'Nora',
        'Oihana', 'Olatz', 'Uxue',
    ];

    protected static $lastName = [
        'Agirre', 'Aguirre', 'Aizpurua', 'Aldave', 'Altuna', 'Aramburu',
        'Aranburu', 'Arana', 'Aranguren', 'Aranzabal', 'Aranzubia', 'Arbizu',
        'Arregi', 'Arrieta', 'Arrizabalaga', 'Arteta', 'Artola', 'Askargorta',
        'Azkue', 'Azpilikueta', 'Balenziaga', 'Barrenetxea', 'Bengoetxea',
        'Berrizbeitia', 'Bilbao', 'Donostia', 'Egaña', 'Eizmendi', 'Elexpuru',
        'Elizalde', 'Elizegi', 'Elizondo', 'Elustondo', 'Errasti', 'Etxabe',
        'Etxaniz', 'Etxarri', 'Etxeberria', 'Etxegarai', 'Etxeita', 'Galarraga',
        'Garaikoetxea', 'Garitano', 'Goenaga', 'Goikoetxea', 'Gorostidi',
        'Gorostiza', 'Ibarluzea', 'Iraola', 'Iraurgi', 'Iriondo', 'Iturbe',
        'Iturralde', 'Iturriaga', 'Jauregi', 'Lakabe', 'Larrañaga', 'Larrazabal',
        'Larrea', 'Lasa', 'Laskurain', 'Lekunberri', 'Letamendia', 'Loinaz',
        'Madariaga', 'Mendizabal', 'Mintegi', 'Mugika', 'Mujika', 'Muniain',
        'Olabarrieta', 'Olaizola', 'Oregi', 'Orueta', 'Otxoa', 'Oyarzabal',
        'Oyarzun', 'Sagarzazu', 'Salaberria', 'Sarriegi', 'Susaeta', 'Txurruka',
        'Unanue', 'Urbieta', 'Uriarte', 'Urrutia', 'Urzelai', 'Urtubia',
        'Uzkudun', 'Yeregi', 'Yurrebaso', 'Zabaleta', 'Zaldua', 'Zarraga',
        'Zelaia', 'Zubeldia', 'Zubizarreta', 'Zugazaga',
    ];
}
