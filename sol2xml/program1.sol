class Main : Object {
  run
    [ |
        "basic operation tests"
      _ := 'test aritmetiky: ' print.
      x := 45.
      y := 5.
      _ := (x asString) print.
      _ := ' : ' print.
      _ := (y asString) print.
      _ := ' = ' print.
      _ := ((x divBy: y) asString) print.
      _ := ' | ' print.
      _ := 'test vypisu: ' print.
      someString := 'ahoj'.
      someStringCon := ' svete'.
      _ := (someString concatenateWith: someStringCon) print.
      _ := ' | ' print.

      ww := String from: '5'.
      w := Integer from: 5.
      _ := ((ww identicalTo: w) asString) print.
      _ := ((ww equalTo: w) asString) print.
      _ := ' (double false should be)| ' print.

      qq := Integer from: 4.
      _ := ((qq identicalTo: w) asString) print. "true"
      _ := ((qq equalTo: w) asString) print. "false"
    ]
}