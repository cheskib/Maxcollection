<?php

namespace Database\Seeders;

use App\Models\KeyName;
use Illuminate\Database\Seeder;

/**
 * Starter watchlist of key names per sport. Owner-editable afterwards in
 * Settings; seeding only runs when the table is empty so edits survive.
 */
class KeyNamesSeeder extends Seeder
{
    private const NAMES = [
        'Baseball' => [
            'Babe Ruth', 'Lou Gehrig', 'Ty Cobb', 'Honus Wagner', 'Cy Young', 'Walter Johnson', 'Christy Mathewson',
            'Jimmie Foxx', 'Mel Ott', 'Rogers Hornsby', 'Satchel Paige', 'Jackie Robinson', 'Ted Williams',
            'Joe DiMaggio', 'Stan Musial', 'Yogi Berra', 'Whitey Ford', 'Duke Snider', 'Mickey Mantle', 'Willie Mays',
            'Hank Aaron', 'Roberto Clemente', 'Ernie Banks', 'Frank Robinson', 'Harmon Killebrew', 'Al Kaline',
            'Sandy Koufax', 'Bob Gibson', 'Carl Yastrzemski', 'Johnny Bench', 'Pete Rose', 'Tom Seaver', 'Nolan Ryan',
            'Reggie Jackson', 'Mike Schmidt', 'George Brett', 'Robin Yount', 'Rod Carew', 'Thurman Munson',
            'Rickey Henderson', 'Cal Ripken', 'Tony Gwynn', 'Wade Boggs', 'Ozzie Smith', 'Kirby Puckett',
            'Don Mattingly', 'Bo Jackson', 'Darryl Strawberry', 'Dwight Gooden', 'Jose Canseco', 'Mark McGwire',
            'Sammy Sosa', 'Roger Clemens', 'Greg Maddux', 'Randy Johnson', 'Pedro Martinez', 'Barry Bonds',
            'Ken Griffey', 'Frank Thomas', 'Chipper Jones', 'Derek Jeter', 'Mariano Rivera', 'Ichiro Suzuki',
            'Albert Pujols', 'Alex Rodriguez', 'David Ortiz', 'Vladimir Guerrero', 'Clayton Kershaw',
            'Justin Verlander', 'Max Scherzer', 'Roy Halladay', 'Mike Trout', 'Bryce Harper', 'Mookie Betts',
            'Aaron Judge', 'Shohei Ohtani', 'Ronald Acuna', 'Juan Soto', 'Fernando Tatis', 'Freddie Freeman',
            'Julio Rodriguez', 'Bobby Witt', 'Gunnar Henderson', 'Elly De La Cruz', 'Paul Skenes',
        ],
        'Football' => [
            'Jim Brown', 'Johnny Unitas', 'Bart Starr', 'Joe Namath', 'Gale Sayers', 'Dick Butkus', 'O.J. Simpson',
            'Fran Tarkenton', 'Roger Staubach', 'Terry Bradshaw', 'Walter Payton', 'Earl Campbell', 'Tony Dorsett',
            'Dan Fouts', 'Joe Montana', 'Dan Marino', 'John Elway', 'Jim Kelly', 'Warren Moon', 'Jerry Rice',
            'Barry Sanders', 'Emmitt Smith', 'Troy Aikman', 'Brett Favre', 'Steve Young', 'Bruce Smith',
            'Reggie White', 'Lawrence Taylor', 'Deion Sanders', 'Randy Moss', 'Terrell Owens', 'Marshall Faulk',
            'LaDainian Tomlinson', 'Kurt Warner', 'Ray Lewis', 'Ed Reed', 'Troy Polamalu', 'Charles Woodson',
            'Peyton Manning', 'Eli Manning', 'Tom Brady', 'Drew Brees', 'Aaron Rodgers', 'Ben Roethlisberger',
            'Michael Vick', 'Cam Newton', 'Adrian Peterson', 'Calvin Johnson', 'Rob Gronkowski', 'Russell Wilson',
            'Odell Beckham', 'Travis Kelce', 'Tyreek Hill', 'Derrick Henry', 'Saquon Barkley', 'Christian McCaffrey',
            'Patrick Mahomes', 'Josh Allen', 'Lamar Jackson', 'Joe Burrow', 'Justin Herbert', "Ja'Marr Chase",
            'Justin Jefferson', 'Micah Parsons', 'C.J. Stroud', 'Caleb Williams', 'Jayden Daniels',
        ],
        'Basketball' => [
            'George Mikan', 'Bill Russell', 'Wilt Chamberlain', 'Oscar Robertson', 'Jerry West', 'Elgin Baylor',
            'Kareem Abdul-Jabbar', 'Julius Erving', 'Pete Maravich', 'Moses Malone', 'Larry Bird', 'Magic Johnson',
            'Isiah Thomas', 'Hakeem Olajuwon', 'Michael Jordan', 'Charles Barkley', 'Karl Malone', 'John Stockton',
            'Patrick Ewing', 'David Robinson', 'Scottie Pippen', 'Reggie Miller', 'Dominique Wilkins', 'Kevin McHale',
            'Dennis Rodman', "Shaquille O'Neal", 'Penny Hardaway', 'Grant Hill', 'Allen Iverson', 'Kobe Bryant',
            'Tim Duncan', 'Kevin Garnett', 'Dirk Nowitzki', 'Steve Nash', 'Vince Carter', 'Tracy McGrady',
            'Ray Allen', 'Paul Pierce', 'Tony Parker', 'Manu Ginobili', 'Dwyane Wade', 'LeBron James',
            'Carmelo Anthony', 'Chris Paul', 'Dwight Howard', 'Kevin Durant', 'Russell Westbrook', 'Stephen Curry',
            'James Harden', 'Kawhi Leonard', 'Kyrie Irving', 'Damian Lillard', 'Giannis Antetokounmpo',
            'Anthony Davis', 'Nikola Jokic', 'Joel Embiid', 'Devin Booker', 'Jayson Tatum', 'Luka Doncic',
            'Trae Young', 'Ja Morant', 'Zion Williamson', 'Anthony Edwards', 'Victor Wembanyama', 'Chet Holmgren',
            'Yao Ming',
        ],
        'Hockey' => [
            'Gordie Howe', 'Maurice Richard', 'Jean Beliveau', 'Bobby Hull', 'Stan Mikita', 'Terry Sawchuk',
            'Jacques Plante', 'Tim Horton', 'Bobby Orr', 'Phil Esposito', 'Ken Dryden', 'Guy Lafleur',
            'Marcel Dionne', 'Mike Bossy', 'Wayne Gretzky', 'Mark Messier', 'Paul Coffey', 'Ray Bourque',
            'Steve Yzerman', 'Mario Lemieux', 'Patrick Roy', 'Brett Hull', 'Joe Sakic', 'Jaromir Jagr',
            'Eric Lindros', 'Dominik Hasek', 'Martin Brodeur', 'Teemu Selanne', 'Peter Forsberg', 'Pavel Bure',
            'Nicklas Lidstrom', 'Alex Ovechkin', 'Sidney Crosby', 'Evgeni Malkin', 'Patrick Kane',
            'Jonathan Toews', 'Steven Stamkos', 'Erik Karlsson', 'Connor McDavid', 'Auston Matthews',
            'Nathan MacKinnon', 'Cale Makar', 'Leon Draisaitl', 'Kirill Kaprizov', 'Connor Bedard',
        ],
        'Soccer' => [
            'Pele', 'Diego Maradona', 'Johan Cruyff', 'Franz Beckenbauer', 'George Best', 'Bobby Charlton',
            'Eusebio', 'Ferenc Puskas', 'Alfredo Di Stefano', 'Michel Platini', 'Zico', 'Marco van Basten',
            'Roberto Baggio', 'Paolo Maldini', 'Zinedine Zidane', 'Ronaldo Nazario', 'Ronaldinho', 'David Beckham',
            'Thierry Henry', 'Kaka', 'Xavi', 'Andres Iniesta', 'Gianluigi Buffon', 'Lionel Messi',
            'Cristiano Ronaldo', 'Neymar', 'Luis Suarez', 'Sergio Ramos', 'Luka Modric', 'Mohamed Salah',
            'Kevin De Bruyne', 'Robert Lewandowski', 'Kylian Mbappe', 'Erling Haaland', 'Jude Bellingham',
            'Vinicius Junior', 'Lamine Yamal', 'Mia Hamm', 'Alex Morgan', 'Megan Rapinoe',
        ],
        'Golf' => [
            'Bobby Jones', 'Ben Hogan', 'Sam Snead', 'Byron Nelson', 'Arnold Palmer', 'Gary Player',
            'Jack Nicklaus', 'Lee Trevino', 'Tom Watson', 'Seve Ballesteros', 'Nick Faldo', 'Greg Norman',
            'Payne Stewart', 'Fred Couples', 'Phil Mickelson', 'Vijay Singh', 'Ernie Els', 'Tiger Woods',
            'Rory McIlroy', 'Jordan Spieth', 'Brooks Koepka', 'Jon Rahm', 'Scottie Scheffler',
            'Annika Sorenstam', 'Nelly Korda',
        ],
        'Tennis' => [
            'Rod Laver', 'Arthur Ashe', 'Bjorn Borg', 'Jimmy Connors', 'John McEnroe', 'Ivan Lendl',
            'Boris Becker', 'Stefan Edberg', 'Andre Agassi', 'Pete Sampras', 'Roger Federer', 'Rafael Nadal',
            'Novak Djokovic', 'Andy Murray', 'Carlos Alcaraz', 'Jannik Sinner', 'Billie Jean King', 'Chris Evert',
            'Martina Navratilova', 'Steffi Graf', 'Monica Seles', 'Venus Williams', 'Serena Williams',
            'Maria Sharapova', 'Naomi Osaka', 'Coco Gauff', 'Iga Swiatek',
        ],
        'Boxing' => [
            'Jack Dempsey', 'Joe Louis', 'Sugar Ray Robinson', 'Rocky Marciano', 'Jake LaMotta', 'Rocky Graziano',
            'Muhammad Ali', 'Joe Frazier', 'George Foreman', 'Sugar Ray Leonard', 'Marvin Hagler', 'Thomas Hearns',
            'Roberto Duran', 'Mike Tyson', 'Evander Holyfield', 'Lennox Lewis', 'Oscar De La Hoya',
            'Floyd Mayweather', 'Manny Pacquiao', 'Canelo Alvarez', 'Tyson Fury', 'Anthony Joshua',
            'Deontay Wilder',
        ],
        'Wrestling' => [
            'Hulk Hogan', 'Andre the Giant', 'Ric Flair', 'Randy Savage', 'Ultimate Warrior', 'Bret Hart',
            'Shawn Michaels', 'Undertaker', 'Stone Cold Steve Austin', 'The Rock', 'Triple H', 'Mick Foley',
            'Kurt Angle', 'Rey Mysterio', 'Eddie Guerrero', 'John Cena', 'Randy Orton', 'Batista',
            'Brock Lesnar', 'CM Punk', 'Roman Reigns', 'Seth Rollins', 'Charlotte Flair', 'Becky Lynch',
            'Ronda Rousey',
        ],
        'Racing' => [
            'Richard Petty', 'Dale Earnhardt', 'Jeff Gordon', 'Jimmie Johnson', 'Tony Stewart', 'Kyle Busch',
            'Denny Hamlin', 'Chase Elliott', 'Kyle Larson', 'Danica Patrick', 'Mario Andretti', 'A.J. Foyt',
            'Rick Mears', 'Al Unser', 'Ayrton Senna', 'Alain Prost', 'Michael Schumacher', 'Lewis Hamilton',
            'Max Verstappen', 'Sebastian Vettel', 'Fernando Alonso', 'Lando Norris', 'Valentino Rossi',
        ],
    ];

    public function run(): void
    {
        if (KeyName::count() > 0) {
            return;
        }

        $rows = [];
        foreach (self::NAMES as $sport => $names) {
            foreach ($names as $name) {
                $rows[] = ['sport' => $sport, 'name' => $name, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        KeyName::insert($rows);
    }
}
