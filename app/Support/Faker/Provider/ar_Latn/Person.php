<?php

namespace App\Support\Faker\Provider\ar_Latn;

use Faker\Provider\Person as BasePerson;

/**
 * Arabic names in Latin script.
 *
 * Faker ships Arabic names in Arabic script (ar_SA, ar_EG, ar_JO), and neither
 * voku/portable-ascii nor ICU's character-level transliteration produces
 * readable Latin forms — Arabic is a consonantal script, so letter-by-letter
 * conversion yields consonant clusters like "sm lHSyn" that no reader would
 * recognise. This provider replaces the Faker Arabic locales with a curated
 * pool of names in the romanisations familiar from football media (Mohamed
 * Salah, Achraf Hakimi, Youssef En-Nesyri, Riyad Mahrez, and the like), mixing
 * Maghreb and Mashriq anthroponymy so Moroccan, Algerian, Egyptian, Saudi,
 * etc. nationalities all draw plausible names. Pool size is deliberately large
 * enough to keep PlayerGeneratorService's 10-attempt collision retry loop from
 * stalling across many seasons.
 */
class Person extends BasePerson
{
    protected static $firstNameMale = [
        'Achraf', 'Adam', 'Adel', 'Ahmed', 'Aissa', 'Ali', 'Amine', 'Amir',
        'Anis', 'Anouar', 'Ayman', 'Ayoub', 'Aziz', 'Bilal', 'Fares', 'Faouzi',
        'Farouk', 'Fayçal', 'Hakim', 'Hamza', 'Hassan', 'Hicham', 'Hussein',
        'Ibrahim', 'Idriss', 'Ilyes', 'Imad', 'Ismail', 'Issam', 'Jamal',
        'Kamel', 'Karim', 'Khaled', 'Lamine', 'Mahdi', 'Mahmoud', 'Marouane', 'Mehdi',
        'Mohamed', 'Mourad', 'Moustafa', 'Nabil', 'Nacer', 'Nordin', 'Omar',
        'Osama', 'Othmane', 'Rachid', 'Ramy', 'Rayan', 'Riyad', 'Saad',
        'Said', 'Salah', 'Sami', 'Samir', 'Sofiane', 'Soufiane', 'Tarek',
        'Tarik', 'Walid', 'Wassim', 'Yacine', 'Yahia', 'Yassine', 'Younes',
        'Youssef', 'Zakaria', 'Ziyad',
    ];

    protected static $firstNameFemale = [
        'Aicha', 'Amina', 'Amira', 'Asma', 'Dounia', 'Fatima', 'Fatin',
        'Hajar', 'Hanane', 'Hiba', 'Imane', 'Jamila', 'Karima', 'Khadija',
        'Laila', 'Latifa', 'Leila', 'Lina', 'Maha', 'Malak', 'Mariam',
        'Maryam', 'Meryem', 'Mouna', 'Nadia', 'Najwa', 'Nesrine', 'Nora',
        'Nour', 'Rania', 'Rim', 'Sabrina', 'Safia', 'Sahar', 'Salma',
        'Samia', 'Sara', 'Selma', 'Sofia', 'Wafa', 'Yasmina', 'Zahra',
        'Zeinab',
    ];

    protected static $lastName = [
        'Alaoui', 'Amrani', 'Benali', 'Benjelloun', 'Bennani', 'Berrada', 'Bouazza',
        'Bouchaib', 'Boukhari', 'Bourkia', 'Chaoui', 'Cherkaoui', 'Daoudi', 'El Amrani',
        'El Fassi', 'El Ghazali', 'El Hadri', 'El Idrissi', 'El Kadiri', 'El Khatib',
        'El Mansouri', 'El Ouali', 'Es-Sabbar', 'Fassi', 'Ghali', 'Guerraoui',
        'Hajji', 'Hakimi', 'Hamdaoui', 'Hamdi', 'Hanafi', 'Harrak', 'Hassani',
        'Ibrahimi', 'Idrissi', 'Jabri', 'Jalal', 'Jamal', 'Jebari', 'Kabbaj',
        'Kadiri', 'Karimi', 'Kettani', 'Khalid', 'Khalifa', 'Lahlou', 'Lahrichi',
        'Lamrani', 'Laraki', 'Lazrak', 'Lebbar', 'Mahfoud', 'Majidi', 'Mansouri',
        'Marrakchi', 'Mekouar', 'Mernissi', 'Messari', 'Mouline', 'Moussaoui',
        'Naciri', 'Nejjar', 'Ouazzani', 'Ouchen', 'Oufkir', 'Raji', 'Rami',
        'Rhazi', 'Riad', 'Saadi', 'Sabbagh', 'Sabri', 'Saidi', 'Salaheddine',
        'Salhi', 'Sebti', 'Sekkat', 'Sentissi', 'Skalli', 'Slaoui', 'Sqalli',
        'Tahiri', 'Tangi', 'Tazi', 'Toumi', 'Touzani', 'Yacoubi', 'Yassine',
        'Zaki', 'Zaoui', 'Zeroual', 'Ziani', 'Zineb',
    ];
}
