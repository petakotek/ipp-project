#!/usr/bin/env python3

# ruff: noqa: S602
"""
An integration testing script for the SOL26 interpreter.

IPP: You can implement the entire tool in this file if you wish, but it is recommended to split
     the code into multiple files and modules as you see fit.

     Below, you have some code to get you started with the CLI argument parsing and logging setup,
     but you are **free to modify it** in whatever way you like.

Author: Ondřej Ondryáš <iondryas@fit.vut.cz>
"""

import argparse
import logging
import os
import pathlib
import subprocess
import sys
from pathlib import Path

from models import (
    TestCaseDefinition,
    TestCaseReport,
    TestCaseType,
    TestReport,
    TestResult,
    UnexecutedReason,
    UnexecutedReasonCode, CategoryReport,
)
from mypy.util import json_dumps

logger = logging.getLogger("main")


class CliArguments(argparse.Namespace):
    """
    Represents the parsed command-line arguments.
    """

    tests_dir: Path
    recursive: bool
    output: Path | None
    dry_run: bool
    include: list[str] | None
    include_category: list[str] | None
    include_test: list[str] | None
    exclude: list[str] | None
    exclude_category: list[str] | None
    exclude_test: list[str] | None
    verbose: int
    regex_filters: bool


def write_result(result_report: TestReport, output_file: Path | None) -> None:
    """
    Writes the final report to the specified output file or standard output if no file is provided.
    """
    result_json = result_report.model_dump_json(indent=2)
    if output_file:
        with output_file.open("w") as f:
            f.write(result_json)
    else:
        print(result_json)


def parse_arguments() -> CliArguments:
    """
    Parses the command-line arguments and performs basic validation a sanitization.
    """

    # Define the CLI arguments
    arg_parser = argparse.ArgumentParser()
    arg_parser.add_argument(
        "tests_dir",
        type=Path,
        help="Path to a directory with the test cases in the SOLtest format.",
    )
    arg_parser.add_argument(
        "-r",
        "--recursive",
        action="store_true",
        help="Recursively search for test cases in subdirectories of the provided directory.",
    )
    arg_parser.add_argument(
        "-o",
        "--output",
        type=Path,
        help="The output file to write the test results to. "
        "If not provided, results will be printed to standard output.",
    )
    arg_parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Perform a dry run: discover the test cases but don't actually execute them.",
    )
    arg_parser.add_argument(
        "-i",
        "--include",
        action="append",
        help="Include only test cases with the specified name or category. "
        "Can be used multiple times to specify multiple criteria."
        "Can be combined with -ic and -it.",
    )
    arg_parser.add_argument(
        "-ic",
        "--include-category",
        action="append",
        help="Include only test cases with the specified category. "
        "Can be used multiple times to specify multiple accepted categories. "
        "Can be combined with -it and -i.",
    )
    arg_parser.add_argument(
        "-it",
        "--include-test",
        action="append",
        help="Include only test cases with the specified name. "
        "Can be used multiple times to specify multiple accepted names. "
        "Can be combined with -ic and -i.",
    )
    arg_parser.add_argument(
        "-e",
        "--exclude",
        action="append",
        help="Exclude test cases with the specified name or category. "
        "Can be used multiple times to specify multiple criteria."
        "Can be combined with -ic and -it.",
    )
    arg_parser.add_argument(
        "-ec",
        "--exclude-category",
        action="append",
        help="Exclude test cases with the specified category. "
        "Can be used multiple times to specify multiple accepted categories. "
        "Can be combined with -it and -i.",
    )
    arg_parser.add_argument(
        "-et",
        "--exclude-test",
        action="append",
        help="Exclude test cases with the specified name. "
        "Can be used multiple times to specify multiple accepted names. "
        "Can be combined with -ic and -i.",
    )
    # arg_parser.add_argument(
    #     "-g",
    #     dest="regex_filters",
    #     action="store_true",
    #     help="When used, the filters specified with -i[ct]/-e[ct] will be interpreted as "
    #     "regular expressions instead of literal strings.",
    # )  # TODO: This is optional. If you don't want to implement it, remove this argument.
    arg_parser.add_argument(
        "-v",
        "--verbose",
        action="count",
        default=0,
        help="Enable verbose logging output (using once = INFO level, using twice = DEBUG level).",
    )

    # Parse the provided arguments
    # argparse will automatically print an error message and exit with the return code 2
    # in case of invalid arguments
    args = arg_parser.parse_args(namespace=CliArguments())

    # Check source directory
    source_directory: Path = args.tests_dir
    if not source_directory.is_dir():
        print("The provided path is not a directory.", file=sys.stderr)
        exit(1)

    # Warn if the output file already exists
    output_file: Path | None = args.output
    if output_file:
        if not output_file.parent.exists():
            print("The parent directory of the output file does not exist.", file=sys.stderr)
            exit(1)
        if output_file.exists():
            logger.warning("The output file will be overwritten: %s", output_file)

    return args


def set_test_type(
    is_int: bool, code_sol: list[int] | None, out_int: list[int] | None
) -> TestCaseType | None:
    if out_int and not code_sol and is_int:
        return TestCaseType.EXECUTE_ONLY
    if not is_int and code_sol and not out_int:
        return TestCaseType.PARSE_ONLY
    if not is_int and code_sol == [0]:
        if out_int:
            return TestCaseType.COMBINED
        return TestCaseType.COMBINED

    return None


def check_for_errors(
    is_int: bool, expected_code_sol: list[int] | None, expected_out_int: list[int] | None
) -> UnexecutedReason | None:
    # kod je xml, ale je vypsan navratovy kod pro sol2xml
    if is_int and expected_code_sol:
        return UnexecutedReason(
            message="Provided parsed code but entered parser return code",
            code=UnexecutedReasonCode.MALFORMED_TEST_CASE_FILE,
        )
    # kod je cisty sol, ale navratova hodnota pro parser neni urcena
    if not is_int and not expected_code_sol:
        return UnexecutedReason(
            message="Provided clean sol code, but return code for sol2xml was not provided.",
            code=UnexecutedReasonCode.MALFORMED_TEST_CASE_FILE,
        )
    # kod je cisty sol, navratova hodnota interpretu uvedena,
    # ale navratova hodnota parseru neni nula (provedeno ok)
    if not is_int and expected_code_sol != [0] and expected_out_int:
        return UnexecutedReason(
            message="Probably you want combine test execution, but code for parser"
            " is not 0 (expecting parser fail). Redundant code for interpreter then.",
            code=UnexecutedReasonCode.MALFORMED_TEST_CASE_FILE,
        )
    return None


def get_test_parameters(
    test_file: Path, in_file: Path | None, out_file: Path | None
) -> TestCase | UnexecutedReason:
    description: str = ""  # popis testu
    category: str = ""  # kategorie testu
    expected_code_sol: list[int] | None = None  # ocekavany navratovy kod sol2xml
    expected_out_int: list[int] | None = None  # ocekavany obsah stdout interpretu
    test_weight: int = -1  # vaha testu
    t_type: TestCaseType | None = None
    src_code: list[str] = []

    is_int = False  # uz interpretovany kod

    with Path.open(test_file) as reader:
        # Read and print the entire file line by line
        for line in reader:
            if line.startswith("***"):
                description = line.replace("***", "").strip()
            if line.startswith("+++"):
                category = line.replace("+++", "").strip()
            if line.startswith("!C!"):
                if expected_code_sol is None:
                    expected_code_sol = []
                expected_code_sol.append(int(line.replace("!C!", "").strip()))
            if line.startswith("!I!"):
                if expected_out_int is None:
                    expected_out_int = []
                expected_out_int.append(int(line.replace("!I!", "").strip()))
            if line.startswith(">>>"):
                test_weight = int(line.replace(">>>", "").strip())
            if line.isspace():
                src_code = reader.readlines()

    # kategorie a vaha testu je povinna
    if not category or not test_weight:
        return UnexecutedReason(
            message="Category or weight not provided.",
            code=UnexecutedReasonCode.MALFORMED_TEST_CASE_FILE,
        )

    # zjisteni typu kodu
    if str(src_code[0]).startswith("<?xml"):
        is_int = True

    # zjisteni, jestli jsou pred tim nez zacneme urcovat typy nekde nepovolene kombinace
    check_error_result = check_for_errors(is_int, expected_code_sol, expected_out_int)
    if isinstance(check_error_result, UnexecutedReason):
        return check_error_result

    t_type = set_test_type(is_int, expected_code_sol, expected_out_int)
    # nepodarilo se urcit typ testu
    if t_type is None:
        return UnexecutedReason(
            message="Test type cannot be determined.",
            code=UnexecutedReasonCode.CANNOT_DETERMINE_TYPE,
        )

    # print(f"code !C! {expected_code_sol}")
    # print(f"code !I! {expected_out_int}")

    # vsechno je v poradku, naplnim tedy test
    return TestCase(
        name=test_file.stem,
        test_source_path=test_file,
        stdin_file=in_file,
        expected_stdout_file=out_file,
        # z TestCaseDefinition
        test_type=t_type,
        description=description,
        category=category,
        points=test_weight,
        expected_parser_exit_codes=expected_code_sol,
        expected_interpreter_exit_codes=expected_out_int,
        src_code=src_code,
    )

# Funkce zapise zdrojovy kod testu do souboru se stejnym nazvem a odpovidajici priponou
# nasledne vraci celou cestu v tomuto souboru
def write_to_file(actual_test: TestCase, suffix: str) -> Path:
    file_full_path = Path(f"outputs/{actual_test.name}{suffix}")
    with Path.open(file_full_path, "w") as tester:  # zapis kodu do souboru
        if actual_test.src_code is not None:
            tester.writelines(actual_test.src_code)

    return file_full_path


# Function runs interpreter on specific file and prints result to stdout
def run_interpreter(filepath: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        f"php ../../int/src/solint.php -s {filepath}",
        shell=True,
        text=True,
        capture_output=True,
        # vypnuti xDebug modu
        env={**os.environ, "XDEBUG_MODE": "off"},
    )


def run_compiler(filepath: Path, output_file : Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        f"python sol_to_xml.py {filepath} > {output_file}",
        shell=True,
        text=True,
    )

def evaluate_test(test_result, test_provided) -> TestCaseReport:
    complete_result: TestResult = TestResult.PASSED

    if test_provided.test_type == TestCaseType.PARSE_ONLY:
        complete_result = TestResult.UNEXPECTED_PARSER_EXIT_CODE
        if test_result.returncode in test_provided.expected_parser_exit_codes:
            complete_result = TestResult.PASSED  # pokud je navratovy kod v ocekavanych

        return TestCaseReport(
            result=complete_result,
            parser_exit_code=test_result.returncode,
            interpreter_exit_code=None,
            parser_stdout=test_result.stdout,
            parser_stderr=test_result.stderr,
            interpreter_stdout=None,
            interpreter_stderr=None,
            diff_output=None,
        )
    # pouze interpreter
    if test_provided.test_type == TestCaseType.EXECUTE_ONLY:
        complete_result = TestResult.UNEXPECTED_INTERPRETER_EXIT_CODE
        if test_result.returncode in test_provided.expected_interpreter_exit_codes:
            complete_result = TestResult.PASSED

        return TestCaseReport(
            result=complete_result,
            parser_exit_code=None,
            interpreter_exit_code=test_result.returncode,
            parser_stdout=None,
            parser_stderr=None,
            interpreter_stdout=test_result.stdout,
            interpreter_stderr=test_result.stderr,
            diff_output=None,
        )


def start_test(actual_test: TestCase) -> TestCaseReport | UnexecutedReason:
    test_folder = Path("outputs/")
    test_result: TestCaseReport
    Path.mkdir(test_folder, parents=True, exist_ok=True)
    # PARSE ONLY
    if actual_test.test_type == TestCaseType.PARSE_ONLY:
        test_final_result_output = TestResult.UNEXPECTED_PARSER_EXIT_CODE
        file_full_path = write_to_file(actual_test, ".sol")

        # spusteni prekladace solu
        output_file = Path(f"outputs/{actual_test.name}.xml")
        result = run_compiler(file_full_path, output_file)

        test_final_result = evaluate_test(result, actual_test)
        # uklid souboru
        Path.unlink(file_full_path, missing_ok=True)
        Path.unlink(output_file, missing_ok=True)
        Path.rmdir(test_folder)
        return test_final_result

    # EXECUTE ONLY
    if actual_test.test_type == TestCaseType.EXECUTE_ONLY:
        test_final_result_output = TestResult.UNEXPECTED_INTERPRETER_EXIT_CODE
        file_full_path = write_to_file(actual_test, ".xml")
        # spusteni interpretu
        result = run_interpreter(file_full_path)
        test_final_result = evaluate_test(result, actual_test)

        Path.unlink(file_full_path, missing_ok=True)
        Path.rmdir(test_folder)
        return test_final_result

    # COMBINATION
    if actual_test.test_type == TestCaseType.COMBINED:
        file_full_path = write_to_file(actual_test, ".sol")

    Path.rmdir(test_folder)

    # neocekavana chyba
    return UnexecutedReason(
        message="Unexpected error.",
        code=UnexecutedReasonCode.OTHER,
    )

# Filtruje testy podle argumentu
def filter_tests(args : CliArguments, discovered_tests : list[TestCase]) -> list[TestCase]:
    result_tests : list[TestCase] = []
    filter_applied = False
    if args.dry_run:
        return discovered_tests
    # pridat podle kategorie nebo jmena
    if args.include:
        filter_applied = True
        for discovered_test in discovered_tests:
            for parameter in args.include:
                parameter = parameter.strip()
                if discovered_test.category == parameter or discovered_test.name == parameter:
                    result_tests.append(discovered_test)

    if args.exclude:
        filter_applied = True
        for discovered_test in discovered_tests:
            for parameter in args.exclude:
                parameter = parameter.strip()
                if discovered_test.category != parameter or discovered_test.name != parameter:
                    result_tests.append(discovered_test)

    # pridani podle kategorie
    if args.include_category:
        filter_applied = True
        for discovered_test in discovered_tests:
            for category in args.include_category:
                category = category.strip()
                if discovered_test.category == category:
                    result_tests.append(discovered_test)

    if args.include_test:
        filter_applied = True
        for discovered_test in discovered_tests:
            for test_name in args.include_test:
                test_name = test_name.strip()
                if  discovered_test.name == test_name:
                    result_tests.append(discovered_test)
    if args.exclude_test:
        filter_applied = True
        for discovered_test in discovered_tests:
            for test_name in args.exclude_test:
                test_name = test_name.strip()
                if  discovered_test.name != test_name:
                    result_tests.append(discovered_test)

    if args.exclude_category:
        filter_applied = True
        for discovered_test in discovered_tests:
            for test_name in args.exclude_category:
                test_name = test_name.strip()
                if discovered_test.name != test_name:
                    result_tests.append(discovered_test)

    # zadny filtr nebyl vybran, vraim vsechny testy
    if not filter_applied:
        return discovered_tests
    # odstraneni duplicit
    seen = set()
    unique = []
    for test in result_tests:
        if test.name not in seen:
            seen.add(test.name)
            unique.append(test)
    return unique

def main() -> None:
    """
    The main entry point for the SOL26 integration testing script.
    It parses command-line arguments and executes the testing process.
    """

    # Set up logging
    # IPP: You do not have to use logging – but it is the recommended practice.
    # See this for more information: https://docs.python.org/3/howto/logging.html
    logging.basicConfig(
        stream=sys.stderr,
        level=logging.WARNING,
        format="%(asctime)s %(levelname)s [%(name)s][%(filename)s:%(lineno)d] %(message)s",
    )

    # Parse the CLI arguments
    args = parse_arguments()

    # Enable debug or info logging if the verbose flag was set twice or once
    if args.verbose >= 2:
        logging.root.setLevel(logging.DEBUG)
    elif args.verbose == 1:
        logging.root.setLevel(logging.INFO)

    # rekurzivne projde vsechny testovaci soubory a ulozi jejich cestu
    if args.recursive:
        dir_list = pathlib.Path.rglob(args.tests_dir, pattern="*")
    else:
        # nerekurzivni pristup
        dir_list = pathlib.Path.iterdir(args.tests_dir)

    test_files: list[Path] = []
    in_files: list[Path] = []
    out_files: list[Path] = []
    # nacteni vsech souboru podle kategorii
    for file in dir_list:
        if file.suffix == ".test":
            test_files.append(file)
        if file.suffix == ".out":
            out_files.append(file)
        if file.suffix == ".in":
            in_files.append(file)

    # vsechny testy, ktere byly nalezeny
    discovered_test_cases: list[TestCase] = []
    # vsechny testy, ktere nebyly spusteny
    unexecuted: dict[str, UnexecutedReason] = {}
    # konecne vysledky
    results: dict[str, CategoryReport] | None = None

    for test_file in test_files:
        test_file = pathlib.Path(test_file)
        # najde soubory stejneho jmena pokud jsou, jinak je oznaci None
        in_file = next((f for f in in_files if f.stem == test_file.stem), None)
        out_file = next((f for f in out_files if f.stem == test_file.stem), None)

        actual_test = get_test_parameters(test_file, in_file, out_file)

        if isinstance(actual_test, TestCase):
            discovered_test_cases.append(actual_test)
        if isinstance(actual_test, UnexecutedReason):
            unexecuted[test_file.stem] = actual_test

    if discovered_test_cases:
        originals = discovered_test_cases
        discovered_test_cases = filter_tests(args, discovered_test_cases)

        # ostatni co nejsou oznacim jako FILTERED_OUT
        if discovered_test_cases and originals:
            for original_test in originals:
                if not original_test in discovered_test_cases:
                    unexecuted[original_test.name] = UnexecutedReason(
                        message="Test was filtered out.",
                        code=UnexecutedReasonCode.FILTERED_OUT
                    )

    test_reports: list[TestCaseReport] = []

    categories: set[str] = set()
    for discovered_test_case in discovered_test_cases:
        categories.add(discovered_test_case.category)

    for category in categories:
        for discovered_test in discovered_test_cases:
            if category == discovered_test.category:
                test_result = start_test(discovered_test)
        
                if isinstance(test_result, TestCaseReport):
                    test_reports.append(test_result)
                if isinstance(test_result, UnexecutedReason):
                    unexecuted[discovered_test.name] = test_result

    # # # Example of how to write the final report:
    report = TestReport(discovered_test_cases=discovered_test_cases, unexecuted=unexecuted, results=results)
    write_result(report, args.output)


class TestCase(TestCaseDefinition):
    """reprezentuje TestCaseDefinition rozsireny o zdrojovy kod testu"""

    src_code: list[str] | None


if __name__ == "__main__":
    main()
