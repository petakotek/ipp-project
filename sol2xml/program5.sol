class A : String {
    metoda [ |
        _ := 'ahoj' print.
    ]
    "atribut [|
        _ := 'ahoj' print.
    ]"
}
class Main : A {
    run [|
        b := [:x|
            _ := (x asString) print.
        ].
        x := super atribut: 5.

        w := self value: 10.
        w := self value: nil.
        _ := b value: 15.
    ]
}