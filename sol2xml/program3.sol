class Main : Object {
  run
    [ |
      b := [ :idx | _ := (idx asString) print. ].
      x := 5 timesRepeat: b.
    ]
}