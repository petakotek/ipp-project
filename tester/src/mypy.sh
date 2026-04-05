case "$1" in
    mypy)
        echo "running mypy ."
        mypy .
        ;;
    check)
        echo "running ruff check"
        ruff check
        ;;
    format)
        echo "formatting code"
        ruff format
        ;;
    *)
        echo "help: $0 {mypy|check|format}"
        exit 1
        ;;
esac