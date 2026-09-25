<?php

    // variables - rules

    // starts with $ sign
    // starts with letters or underscore (_) right after $ dollar
    // Must not start with a space, number or any special right after $ dollar

    $name = "Sana"; // String -> data text, sequence of characters.
    $age = 24; // int -> whole numbers
    $percentage = 77.43; // float -> decimal points
    $is_passed = true; // booleans -> two values true or false, ha ya na, on y off
    $number = null; // NULL -> empty, nothing to store

    // echo var_dump(12);
    // echo var_dump("waseem");

    // echo var_dump($name);
    // echo var_dump($age);
    // echo var_dump($percentage);
    // echo var_dump($is_passed);
    // echo var_dump($number);

    // echo var_dump("Ali" + "Hyder"); 
    // echo var_dump("Ali" . "Hyder"); 
    // echo var_dump(false + 1); // error, blank, 0, 1


    //_______________________
    // OPERATORS 

    // comparison (<, >, ==, ===, !=, !==, <=, >=) // compares two expressions or, two values and returns true or false
    // arithematic (-, +, /, *)
    // assignment (=, +=, -=, /=, *=)
    // logical (&& AND, || OR, !, XOR)
    // increament & decreament (--, ++)

    // echo 6 > 5; // true == 1;
    // echo 5 > 5; // false == 0 == "" == null;

    // $x = true > 0;
    // $x = 5 == 8;
    // $x = 5 != 8;
    // $x = 5 >= 8;
    // $x = 5 >= 5;
    
    // echo $x;

    // echo 3 + 5;
    // echo 3 - 5;
    // echo 3 / 5;
    // echo 3 * 5;

    // $x = 13 + 5;
    // echo $x;

    // $x = 5;

    // echo "Initial value: $x <br/>";
    // $x++;
    // echo "After Increament: $x <br/>";

    // echo $x++; //5, 6
    // echo "<br/>";
    // echo $x; // 6    
    
    // echo $x--;
    // echo "<br/>"; 
    // echo $x;

    // $x = 8;

    // echo ++$x;
    // $x--;
    // echo ++$x;
    // echo ++$x;

    // echo --$x;

    // $x = false;
    // ++$x;
    // echo $x; // blank

    // if (true) {
    //     echo "if block";
    // }
    // if (false) {
    //     echo "if block";
    // }

    // if (true && True) {
    //     echo "if block";
    // }

    // if (false && True) {
    //     echo "if block";
    // }

    // $x = 5;
    // if ($x > 6 || $x <= 5) {
    //     echo "if block";
    // }


    // $x = 0;

    // if ($x++) {
    //     echo $x++;
    // }

    // echo $x;

    echo "<hr/>";
    echo "end of file";
?>