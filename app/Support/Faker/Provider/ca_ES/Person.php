<?php

namespace App\Support\Faker\Provider\ca_ES;

use Faker\Provider\Person as BasePerson;

/**
 * Catalan Person provider for Faker.
 *
 * Used for youth academy and replenishment name generation at Catalan clubs
 * (FC Barcelona, RCD Espanyol, Girona — see
 * {@see \App\Modules\Squad\Configs\TeamRegionalOrigins}). Faker ships no
 * native ca_ES locale, so this provider fills that gap. Nationality stays
 * "Spain" at the data layer; only the name source changes.
 *
 * Pools cover typical Catalan given names (Jordi, Pau, Marc, Arnau…) and
 * surnames (Puig, Solé, Ferrer, Puyol, Fàbregas…). Accents and Catalan
 * orthography are preserved verbatim.
 */
class Person extends BasePerson
{
    protected static $firstNameMale = [
        'Aleix', 'Aniol', 'Arnau', 'Bernat', 'Biel', 'Blai', 'Bru', 'Cesc',
        'Daniel', 'Dídac', 'Eloi', 'Enric', 'Eudald', 'Ferran', 'Francesc',
        'Gabriel', 'Genís', 'Gerard', 'Guerau', 'Guillem', 'Ignasi',
        'Isaac', 'Iu', 'Jan', 'Joan', 'Joaquim', 'Joel', 'Jordi', 'Josep',
        'Lluc', 'Lluís', 'Manel', 'Marc', 'Martí', 'Miquel', 'Narcís',
        'Nicolau', 'Oleguer', 'Oriol', 'Òscar', 'Pau', 'Pere', 'Pol',
        'Quim', 'Ramon', 'Roc', 'Roger', 'Salvador', 'Sergi', 'Tomàs',
        'Toni', 'Valentí', 'Vicenç', 'Xavi', 'Xavier',
    ];

    protected static $firstNameFemale = [
        'Aina', 'Alba', 'Anna', 'Berta', 'Blanca', 'Bruna', 'Carla', 'Clara',
        'Elisenda', 'Emma', 'Esther', 'Gemma', 'Glòria', 'Helena', 'Irene',
        'Joana', 'Judit', 'Laia', 'Laura', 'Marina', 'Marta', 'Mireia',
        'Montserrat', 'Núria', 'Ona', 'Paula', 'Queralt', 'Roser', 'Sílvia',
    ];

    protected static $lastName = [
        'Abellán', 'Agustí', 'Alcàcer', 'Alsina', 'Aragonès', 'Arnau',
        'Badia', 'Balagué', 'Ballart', 'Bardají', 'Barnils', 'Bartra',
        'Benet', 'Bofill', 'Bonet', 'Borrell', 'Bosch', 'Busquets',
        'Cabré', 'Cabrera', 'Camps', 'Capdevila', 'Carbonell', 'Cardús',
        'Carreras', 'Casals', 'Castells', 'Cerdà', 'Codina', 'Colom',
        'Cruyff', 'Cunill', 'Dalmau', 'Domènech', 'Duran', 'Espinosa',
        'Fàbregas', 'Farriol', 'Ferran', 'Ferrer', 'Figueras', 'Font',
        'Gabarró', 'Garriga', 'Gasol', 'Gelabert', 'Genís', 'Gironès',
        'Grau', 'Guardiola', 'Guilera', 'Guimerà', 'Illa', 'Iniesta',
        'Junqueras', 'Llach', 'Llopis', 'Llorens', 'Maragall', 'Martí',
        'Martorell', 'Masip', 'Mestres', 'Miralles', 'Miró', 'Montaner',
        'Munné', 'Oliveras', 'Oliu', 'Orriols', 'Padrós', 'Parellada',
        'Pascual', 'Piqué', 'Planas', 'Pons', 'Portabella', 'Prat',
        'Puig', 'Puigdemont', 'Pujol', 'Puyol', 'Queralt', 'Ramis',
        'Ramoneda', 'Reverter', 'Ribas', 'Ribó', 'Riera', 'Roca', 'Roig',
        'Roure', 'Sala', 'Sallent', 'Salom', 'Salvadó', 'Sanahuja',
        'Sans', 'Sastre', 'Saurí', 'Serra', 'Serrat', 'Solé', 'Soler',
        'Subirachs', 'Tarragó', 'Torras', 'Torrents', 'Trias', 'Vallès',
        'Valls', 'Ventura', 'Vila', 'Vilanova', 'Vives', 'Xirinacs',
    ];
}
