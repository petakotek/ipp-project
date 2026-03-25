class A : Object {
    aa: [ :x |
        _ := 'ahoj A ' print.
        _ := 'hodnota x: ' print.
        _ := (x asString) print.
        _ := ' ' print.
    ]
}

class B : A {
    bb [ |
        _ := 'ahoj B' print.
    ]
}
class Main : Object {
  run
    [ |
      q := A new.
      c := B new.
      _ := c aa: 5.
      _ := c bb.
    ]
}