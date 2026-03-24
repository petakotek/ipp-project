class Main : Object {
  run
    [ |
      a := self foo: 4.
      _ := (a asString) print.
    ]
  foo: [:n |
        x := n plus: 10.
        _ := x plus: 1.
        y := x plus: 2.
        yy := x plus: 3.
        _ := 'foo'.
  ]
}