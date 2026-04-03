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
from enum import Enum
from pathlib import Path

from models import TestReport

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


def get_test_parameters(filepath):
    description: str = "" # popis testu
    category: str = ""  # kategorie testu
    expected_code_sol: int = -1  # ocekavany navratovy kod sol2xml
    expected_out_int: list = []  # ocekavany obsah stdout interpretu
    test_weight: int = -1  # vaha testu
    t_type: TestType = TestType.UNSPECIFIED
    src_code : list = []

    is_int = False # uz interpretovany kod

    with Path.open(filepath) as reader:
        # Read and print the entire file line by line
        for line in reader:
            if line.startswith("***"):
                description = line.replace("***", "").strip()
            if line.startswith("+++"):
                category = line.replace("***", "").strip()
            if line.startswith("!C!"):
                expected_code_sol = int(line.replace("!C!", "").strip())
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
        t_type = TestType.ONLY_INT

    # kod je sol ale neni speficifovan navratovy kod sol2xml
    if not is_int and expected_code_sol == -1:
        print("kod je sol ale neni speficifovan navratovy kod sol2xml", file=sys.stderr)
        return None

    # sol2xml ne, interpret jo, kod neni xml
    if expected_code_sol != -1 and expected_out_int and not is_int:
        t_type = TestType.BOTH
    if not expected_out_int and not is_int:
        t_type = TestType.ONLY_SOL2XML
    return Test(description, category, expected_code_sol, expected_out_int, test_weight, t_type)


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

    for file in dir_list:
        if file.suffix == ".test":
            get_test_parameters(file)
    #
    # Enable debug or info logging if the verbose flag was set twice or once
    if args.verbose >= 2:
        logging.root.setLevel(logging.DEBUG)
    elif args.verbose == 1:
        logging.root.setLevel(logging.INFO)

    # # # Example of how to write the final report:
    # report = TestReport(discovered_test_cases=[], unexecuted={}, results={})
    # write_result(report, args.output)



class TestType(Enum):
    """Reprezentuje enumerator typu testu"""
    UNSPECIFIED = 0,
    ONLY_SOL2XML = 1,
    ONLY_INT = 2,
    BOTH = 3



# trida reprezentujici jeden test
class Test:
    """Reprezentuje strukturu jednoho testu"""
    description : str # popis testu
    category: str # kategorie testu
    expected_return_code_sol : list # ocekavany navratovy kod sol2xml
    expected_return_out_int : str # ocekavany obsah stdout interpretu
    test_weight : int # vaha testu

    t_type: TestType

    def __init__(self, description, category, code_sol, out_int, test_weight, t_type) -> None:
        self.description = description
        self.category = category
        self.expected_return_code_sol = code_sol
        self.expected_return_out_int = out_int
        self.test_weight = test_weight
        self.t_type = t_type



if __name__ == "__main__":
    main()
