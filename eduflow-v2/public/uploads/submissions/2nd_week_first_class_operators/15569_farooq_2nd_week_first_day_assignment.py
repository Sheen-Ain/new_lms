# Arithmetic Operators
a = 15
b = 4
print(a+b, a-b, a*b, a/b, a//b, a**b)

print(17//3)
print(17/3)

print(5*3)
print(2*4)

print(10/3)
print(10%3)

# Area of rectangle
length = 5
width = 3
area = length * width
print(area)

# Operator precedence
print(10 + 5*2)      # 20
print((10 + 5)*2)    # 30

# Odd Even Pattern
print(9%2, 10%2, 11%2)

# Division + Modulus
print(100//7, 100%7)

# Power
base = 7
exponent = 3
print(base**exponent)

# Negative numbers
a = (-5)*2
b = (-5*2)
print("(-5)*2 =", a)
print("-5*2 =", b)

# Assignment Operators
x = 10
x += 5
x -= 3
x *= 2
print(x)

a = 20
a //= 3
a %= 4
print(a)

# Compound Assignment
y = 5
y *= 3
print(y)

# Question 14
score = 50
score += 10
score *= 2
score -= 15
print(score)

# Question 15
num = 7*5/2
print(num)

# Question 16
z = 100
z **= 2
z //= 10
print(z)

# Question 17
p = 5
p += 5
print(p)

# Question 18
fa = 100
fa += 10
print(fa)

# Question 19
a = 10
b = 3
a *= b
a += 5
print(a)

x, y, z = 5, 10, 15
x += y
z *= 2
print(z)

# Comparison Operators
print(10==10, 10!=5, 7>3, 4<8, 5>=5, 6<=10)

# Fixed line
a = 15
b = 20
print(a>b, a<=b)

# Comparison
ash = 22
fa = 19
print(ash == fa)

sa = 22
print(ash == sa)

# String vs int
print(5 == "5")

# == vs is
x = 10
y = 10.0
print(x == y)
print(x is y)

# Input example
num1 = int(input("Enter first number: "))
num2 = int(input("Enter second number: "))

if num1 < num2:
    print(num1, "is less than", num2)
else:
    print(num1, "is greater or equal to", num2)

# Logical expression
result = (10+5) > (3*4)
print(result)

# Equal check
a = 7
b = 7
print(a == b)
# This is a sample Python script.

# Press Shift+F10 to execute it or replace it with your code.
# Press Double Shift to search everywhere for classes, files, tool windows, actions, and settings.


def print_hi(name):
    # Use a breakpoint in the code line below to debug your script.
    print(f'Hi, {name}')  # Press Ctrl+F8 to toggle the breakpoint.


# Press the green button in the gutter to run the script.
if __name__ == '__main__':
    print_hi('PyCharm')

# See PyCharm help at https://www.jetbrains.com/help/pycharm/
