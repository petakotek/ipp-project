class A : Object {
    aa: [ :x |
        _ := 'ahoj A ' print.
        _ := 'hodnota x: ' print.
        _ := (x asString) print.
        _ := ' ' print.
        _ := 'ted to prijde: ' print.
        _ := self foo.
        _ := ' ' print.
    ]
    foo [ |
        _ := 'ahoj foo A' print.
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
      haha := [ | _ := self foo. ].
      _ := haha value.
      _ := ' | testoval jsem | ' print.
      q := B new.
      c := B new.
      _ := c aa: 5.
      _ := self foo.
      _ := ' |= ' print.
      _ := q foo.
    ]
  foo [ |
     _ := 'ahoj foo Main' print.
  ]
}