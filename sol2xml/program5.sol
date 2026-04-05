class Main : Object {
  run
    [ |
         b := [| ].
         r2 := b value.
      "or: - levy operand false, pravy blok se vyhodnocuje"
              r4 := false or: [| _ := true. ].
              _ := (r4 asString) print.
    ]
}