class Calculator : Object {
    apply:a:b: [ :op :a :b |
        r := op value: a value: b.
    ]
}

class Main : Object {
    run [ |
        calc := Calculator new.
        addBlock := [ :x :y | r := x plus: y. ].
        mulBlock := [ :x :y | r := x multiplyBy: y. ].

        re1 := addBlock value: 5 value: 6.
        _ := (re1 asString) print.
    ]
}
