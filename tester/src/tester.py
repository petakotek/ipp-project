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
import sol_to_xml
from pathlib import Path


from models import TestReport, TestCaseDefinition, TestCaseType, TestResult

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


# Function runs interpreter on specific file and prints result to stdout
def run_interpreter(filepath) -> None:
    proc = subprocess.Popen(
        f"php ../../int/src/solint.php -s {filepath}",
        shell=True,
        stdout=subprocess.PIPE,
        # vypnuti xDebug modu
        env={**os.environ, "XDEBUG_MODE": "off"},
    )
    script_response = proc.stdout.read()
    print(script_response.decode("utf-8"))


def get_test_parameters(test_file, in_file, out_file):
    description: str = ""  # popis testu
    category: str = ""  # kategorie testu
    expected_code_sol: list | None = []  # ocekavany navratovy kod sol2xml
    expected_out_int: list | None = []  # ocekavany obsah stdout interpretu
    test_weight: int = -1  # vaha testu
    t_type: TestCaseType = TestCaseType.EXECUTE_ONLY
    src_code: list = []

    is_int = False  # uz interpretovany kod

    with Path.open(test_file) as reader:
        # Read and print the entire file line by line
        for line in reader:
            if line.startswith("***"):
                description = line.replace("***", "").strip()
            if line.startswith("+++"):
                category = line.replace("***", "").strip()
            if line.startswith("!C!"):
                expected_code_sol.append(line.replace("!C!", "").strip())
            if line.startswith("!I!"):
                expected_out_int.append(line.replace("!I!", "").strip())
            if line.startswith(">>>"):
                test_weight = int(line.replace(">>>", "").strip())
            if line.isspace():
                src_code = reader.readlines()

    # kategorie a vaha testu je povinna
    if not category or not test_weight:
        # print("No category or weight provided.", file=sys.stderr)
        return None

    if str(src_code[0]).startswith("<?xml"):
        is_int = True

    if is_int:
        t_type = TestCaseType.EXECUTE_ONLY

    # kod je sol ale neni speficifovan navratovy kod sol2xml
    if not is_int and not expected_code_sol:
        print("kod je sol ale neni speficifovan navratovy kod sol2xml", file=sys.stderr)
        return None

    # sol2xml ne, interpret jo, kod neni xml
    if expected_code_sol and expected_out_int and not is_int:
        t_type = TestCaseType.COMBINED
    if not expected_out_int and not is_int:
        t_type = TestCaseType.PARSE_ONLY

    if not expected_code_sol:
        expected_code_sol = None
    if not expected_out_int:
        expected_out_int = None

    test = TestCase(
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
    return test


def write_to_file(actual_test, suffix):
    file_full_path = actual_test.test_source_path.with_suffix(suffix)
    with open(file_full_path, "w") as tester:  # zapis kodu do souboru
        tester.writelines(actual_test.src_code)

    return file_full_path


def start_test(actual_test):
    if actual_test.test_type == TestCaseType.PARSE_ONLY:
        file_full_path = write_to_file(actual_test, ".sol")

        result = subprocess.run(
            f"../.venv/bin/python sol_to_xml.py {file_full_path} > tmp.xml",
            shell=True,
            text=True,
        )
        if result.returncode in actual_test.expected_parser_exit_codes:
            print(TestResult.PASSED)
        else:
            print(TestResult.UNEXPECTED_PARSER_EXIT_CODE)

    if actual_test.test_type == TestCaseType.EXECUTE_ONLY:
        file_full_path = write_to_file(actual_test, ".xml")

    if actual_test.test_type == TestCaseType.COMBINED:
        file_full_path = write_to_file(actual_test, ".sol")


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

    # prazdny list pro vsechny soubory ve slozce
    dir_list = []
    dir_list = pathlib.Path.iterdir(args.tests_dir)
    # pole kde budou ulozeny instance testu
    tests_list = []

    test_files: list | None = []
    in_files: list | None = []
    out_files: list | None = []
    # nacteni vsech souboru podle kategorii
    for file in dir_list:
        if file.suffix == ".test":
            test_files.append(file)
        if file.suffix == ".out":
            out_files.append(file)
        if file.suffix == ".in":
            in_files.append(file)

    actual_test: TestCase
    for test_file in test_files:
        test_file = pathlib.Path(test_file)
        # najde soubory stejneho jmena pokud jsou, jinak je oznaci None
        in_file = next((f for f in in_files if f.stem == test_file.stem), None)
        out_file = next((f for f in out_files if f.stem == test_file.stem), None)

        actual_test = get_test_parameters(test_file, in_file, out_file)
        start_test(actual_test)

    # Enable debug or info logging if the verbose flag was set twice or once
    if args.verbose >= 2:
        logging.root.setLevel(logging.DEBUG)
    elif args.verbose == 1:
        logging.root.setLevel(logging.INFO)

    # # # Example of how to write the final report:
    # report = TestReport(discovered_test_cases=[], unexecuted={}, results={})
    # write_result(report, args.output)


# vlastni struktura TestCase, ktera je jeste rozsirena o zdrojovy kod
class TestCase(TestCaseDefinition):
    src_code: list | None = []


if __name__ == "__main__":
    main()
