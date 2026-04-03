class A : Object {
    m: [:x |
        _ := x print.
    ]
    r [|
        _ := self print.
    ]
}
class B : A {
    m: [:x |
        _ := super m: 'ahoj'.
        _ := x print.
    ]
}
class C : B {
    u [|
        _ := self m: super.
        _ := 'bar' print.
    ]
    print [|
        _ := 'bar' print.
    ]
}
class Main : Object {
  run
    [ |

        x := 5.
        ee := [:x | _ := (x asString) print. ].
        _ := ee value: x.
      ]
}