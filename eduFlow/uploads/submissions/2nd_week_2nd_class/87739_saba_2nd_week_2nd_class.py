#Q:01
a = True
b = False

print(a and b)
print(a or b)
print(not a)

#Q:02
#positive,negative digit
num = int(input("Enter a number: "))

if num > 0:
    print("Positive")
elif num < 0:
    print("Negative")
else:
    print("Zero")

    #Q:03
    #List
    fruits = ["apple", "banana", "mango"]

    print("grapes" in fruits)

#Q:04
#voting
age = int(input("Enter your age: "))

if age >= 18:
    print("You can vote")
else:
    print("You cannot vote")

#Q:05
#variables
x = 10
y = 20

print(x > 5 and y < 30)

#Q:06
#check vowel
ch = input("Enter a character: ")

if ch in "aeiouAEIOU":
    print("Vowel")
else:
    print("Not a vowel")

#Q:07
#even,odd
num = int(input("Enter number: "))

if num % 2 == 0 and num > 50:
    print("Big Even Number")
elif num % 2 == 0 and num <= 50:
    print("Small Even Number")
elif num % 2 != 0 and num > 50:
    print("Big Odd Number")
else:
    print("Small Odd Number")

#Q:08
#marks grading
marks = int(input("Enter marks: "))

if marks > 90:
    print("Grade A")
    if marks > 95:
        print("Outstanding!")
elif marks > 80:
    print("Grade B")
elif marks > 60:
    print("Grade C")
else:
    print("Fail")

#Q:09
#student
students = ["Ali", "Sara", "Ahmed", "Zainab", "Bilal"]

name = input("Enter name: ")

if name in students and len(name) > 5:
    print("Valid Senior Student")
else:
    print("Not Valid")

#Q:10
#temperature check
temp = float(input("Enter temperature: "))

if temp > 35:
    print("Very Hot")
elif 25 <= temp <= 35:
    print("Hot")
elif 15 <= temp < 25:
    print("Pleasant")
elif 5 <= temp < 15:
    print("Cold")
else:
    print("Very Cold")

#Q:11
#Login system
username = input("Enter username: ")
password = input("Enter password: ")

if username == "admin":
    if password == "12345":
        print("Login Successful")
    else:
        print("Wrong Password")
else:
    print("Invalid Username")

#Q:12
#Largest number
a = int(input("Enter a: "))
b = int(input("Enter b: "))
c = int(input("Enter c: "))
if a > b:
    if a > c:
        print("Largest:", a)
    else:
        print("Largest:", c)
else:
    if b > c:
        print("Largest:", b)
    else:
        print("Largest:", c)

#Q:13
#Grading system
per = float(input("Enter percentage: "))

if per >= 90:
    if per == 100:
        print("Perfect Score - A+")
    else:
        print("A Grade")
elif per >= 80:
    print("B Grade")
elif per >= 70:
    print("C Grade")
else:
    print("Below Average")
