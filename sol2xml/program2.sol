class Main : Object {
  run
    [ |
      b0 := [ | _ := ' ahoj z bloku ' print. ].
      b1 := [ :x :y |
            a := x plus: 1.
            b := y.
            xx := a plus: b.
      ].
      a := b1 value: 3 value: 5.
      _ := (a asString) print.
      _ := ' ' print.
      xx := self foo: ('5' asInteger) f: 4.

      _ := b0 value.
    ]
  foo:f: [:x :y |
     _ := 'Dusan says: ' print.
     w := x plus: y.
     _ := (x asString) print.
     _ := ' + ' print.
     _ := (y asString) print.
     _ := ' = ' print.
     _ := (w asString) print.
     "abych vratil to w"
     _ := w.
  ]
}