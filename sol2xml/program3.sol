class Main : Object {
  run
    [ |
      x := 0.
      b := [ | x := 1.  y := 2. ].
      _ := ((b value) asString) print.
      _ := (x asString) print.
    ]
}