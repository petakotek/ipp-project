class A : String{
    main [|]
}

class Main : Object {
    run [ |
        x1 := String read.
        x2 := String read.
        _ := x1 print.
        _ := '\n' print.
        _ := x2 print.
    ]
}
