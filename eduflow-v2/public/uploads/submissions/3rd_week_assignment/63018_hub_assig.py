#Section 1: Basics and Variables (1 to 8)
#1
name = "hubali"
print("name:",name)
print(len(name))

#2
a = 25 #int
b = 3.5 #float
print("sum: ",a+b)
print("difference: ",a-b)
print("product: ",a*b)

#3
username = input("Enter your username: ")
age = input("Enter your age: ")
print(f"hello {username}, ap ky age {age} saal ha")

#4
n = 100
n_str = str(n)
print(n_str)
print(type(n_str))

#5
num1 = float(input("enter number "))
num2 = float(input("enter number2 "))
average = (num1 + num2) /2
print(average)

#6
x,y,z = 10,20,30
print(x,y,z)

#7
sentence = "python is fun"
print("fun"in sentence)

#8
num = int(input("Enter number: "))
num = int(num)
print("square",num ** 2)

#Section 2: Operators (9 to 15)
#9
a =15
b =4
print("sum",a+b)
print("subtraction",a-b)
print("division",a/b)
print("floor division",a//b)
print("modulus",a%b)
print("multiplication",a*b)

#10
score = 50
score += 10
print(score)
score *= 2
print(score)
score -= 15
print(score)

#11
x = 10
y = 20
print(x == y)
print(x != y)
print(x > y)
print(x < y)
print(x >= y)
print(x <= y)

#12
a = True
b = False
print(a and b)
print(a or b)
print(not a)
print(not b)

#13
fruits = ["apple", "banana", "mango", "orange"]
fruit = input("Enter fruit name: ")
print(fruit in fruits)
print(fruit not in fruits)

#14
num = 7
num **= 2
num //= 3
num %= 5
print("Final Value:", num)

#15
result = 10 + 5 * 3 - 8 / 2 ** 2
print("Result:", result)


# Section 3: If-Elif-Else and Nested If
#16
num = int(input("Enter a number: "))
if num % 2 == 0:
    print("Even Number")
else:
    print("Odd Number")

#17
age = int(input("Enter age: "))
if age >= 18:
    print("You are eligible to vote")
else:
    print("You are not eligible")

#18
temp = float(input("Enter temperature in Celsius: "))
if temp > 35:
    print("Very Hot")
elif temp >= 25:
    print("Hot")
elif temp >= 15:
    print("Pleasant")
else:
    print("Cold")

#19
char = input("Enter a character: ").lower(),
if char in "aeiou":
    print("Vowel")
else:
    print("Consonant")

#20
num1 = int(input("Enter first number: "))
num2 = int(input("Enter second number: "))
if num1 > num2:
    print("Bigger number is:", num1)
else:
    print("Bigger number is:", num2)

#21
marks = int(input("Enter marks: "))
if marks >= 90:
    print("A")
elif marks >= 80:
    print("B")
elif marks >= 70:
    print("C")
elif marks >= 60:
    print("D")
else:
    print("Fail")

#22
num = int(input("Enter number: "))
if num > 0 and num % 2 == 0:
    print("Positive Even",num)

#23
a = int(input("Enter first number: "))
b = int(input("Enter second number: "))
c = int(input("Enter third number: "))
smallest = min(a, b, c)
print("Smallest number is:", smallest)

#24
num = int(input("Enter number: "))
if num % 3 == 0 and num % 5 == 0:
    print("Divisible by 15")
elif num % 3 == 0:
    print("Divisible by 3",num)
elif num % 5 == 0:
    print("Divisible by 5",num)
else:
    print("Not divisible by 3 or 5")

#25
age = int(input("Enter age: "))
if age >= 18:
    if age >= 60:
        print("Senior Citizen")
    else:
        print("Adult")
else:
    print("Minor")

#26
username = input("Enter username: ")
password = input("Enter password: ")
if username == "student":
    if password == "pass123":
        print("Login Successful")
    else:
        print("Wrong Password")
else:
    print("Invalid Username")

# 27
text = input("Enter a string: ")
if len(text) > 10:
    if "python" in text.lower():
        print("Valid Long Python String")
    else:
        print("Long String")
else:
    print("Short String")

#28
a = int(input("Enter first number: "))
b = int(input("Enter second number: "))
c = int(input("Enter third number: "))

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

#29
percentage = float(input("Enter percentage: "))
if percentage >= 90:
    if percentage == 100:
        print("Perfect A+")
    else:
        print("A Grade")
elif percentage >= 80:
    print("B Grade")
elif percentage >= 70:
    print("C Grade")
else:
    print("Needs Improvement")